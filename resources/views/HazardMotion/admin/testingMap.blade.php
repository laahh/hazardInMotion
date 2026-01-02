{{-- @extends('layouts.masterMotionHazardAdmin') --}}
@extends('layouts.master-home')

@section('title', 'Hazard Detection - Beraucoal')

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    /* Filter Dropdown Styles */
    .btn-filter {
        border: 1px solid #e5e7eb;
        background-color: #ffffff;
        color: #374151;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    
    .btn-filter:hover {
        background-color: #f9fafb;
        border-color: #d1d5db;
        color: #111827;
    }
    
    .btn-filter:focus {
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
    }
    
    .btn-filter.dropdown-toggle::after {
        margin-left: 0.5rem;
    }
    
    .dropdown-menu {
        border: 1px solid #e5e7eb;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        border-radius: 8px;
        padding: 0.5rem 0;
        margin-top: 0.5rem;
    }
    
    .dropdown-item.filter-option {
        padding: 0.5rem 1rem;
        transition: all 0.15s ease;
    }
    
    .dropdown-item.filter-option:hover {
        background-color: #f3f4f6;
        color: #111827;
    }
    
    .dropdown-item.filter-option:active {
        background-color: #e5e7eb;
    }
    
    /* Layer Toggle Button Styles */
    .layer-toggle-btn {
        position: relative;
    }
    
    .layer-toggle-btn.active {
        background-color: #3b82f6;
        color: #ffffff;
        border-color: #3b82f6;
    }
    
    .layer-toggle-btn.active:hover {
        background-color: #2563eb;
        border-color: #2563eb;
    }
    
    .layer-toggle-btn:not(.active) {
        background-color: #ffffff;
        color: #6b7280;
        opacity: 0.7;
    }
    
    .layer-toggle-btn:not(.active):hover {
        background-color: #f9fafb;
        opacity: 1;
    }
    
    /* Icon Toolbar - Left Side - Modern Clean Style */
    .map-icon-toolbar {
        position: absolute;
        top: 90px;
        left: 20px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 6px;
        background: #ffffff;
        border-radius: 12px;
        padding: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 1px solid rgba(0, 0, 0, 0.06);
        transition: all 0.3s ease;
    }
    
    /* Icon Toolbar Button - Clean Modern Style */
    .icon-toolbar-btn {
        position: relative;
        width: 64px;
        min-height: 64px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
        color: #64748b;
        padding: 10px 8px;
        gap: 6px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    }
    
    .icon-toolbar-btn:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #334155;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        transform: translateY(-1px);
    }
    
    .icon-toolbar-btn.active {
        background: #3b82f6;
        border-color: #3b82f6;
        color: #ffffff;
        box-shadow: 0 2px 6px rgba(59, 130, 246, 0.25);
    }
    
    .icon-toolbar-btn.active:hover {
        background: #2563eb;
        border-color: #2563eb;
    }
    
    .icon-toolbar-btn i {
        font-size: 26px;
        transition: transform 0.15s ease;
        line-height: 1;
    }
    
    .icon-toolbar-btn:hover i {
        transform: scale(1.05);
    }
    
    .icon-toolbar-btn.active i {
        transform: none;
    }
    
    .icon-toolbar-btn .btn-label {
        font-size: 11px;
        font-weight: 500;
        text-align: center;
        line-height: 1.2;
        color: inherit;
        margin-top: 2px;
    }
    
    /* Badge for count - Subtle Style */
    .icon-toolbar-badge {
        position: absolute;
        top: 4px;
        right: 4px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        background: #ef4444;
        color: #ffffff;
        border-radius: 9px;
        font-size: 10px;
        font-weight: 600;
        display: none;
        align-items: center;
        justify-content: center;
        border: 2px solid #ffffff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        z-index: 10;
    }
    
    .icon-toolbar-badge:not(:empty) {
        display: flex;
    }
    
    .icon-toolbar-btn.active .icon-toolbar-badge {
        background: #ffffff;
        color: #3b82f6;
        border-color: #3b82f6;
        box-shadow: 0 1px 3px rgba(59, 130, 246, 0.3);
    }
    
    /* Divider - Subtle */
    .icon-toolbar-divider {
        height: 1px;
        background: #e5e7eb;
        margin: 6px 0;
        border: none;
    }
    
    /* Map Sidebar Panel Styles - Right Side */
    .map-sidebar {
        position: absolute;
        top: 90px;
        right: 0;
        width: 420px;
        height: calc(100% - 90px);
        background: #ffffff;
        box-shadow: -4px 0 16px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        display: flex;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        transform: translateX(0);
        border-top-left-radius: 20px;
        border-bottom-left-radius: 20px;
    }
    
    @media (max-width: 1200px) {
        .map-icon-toolbar {
            top: 85px;
            left: 15px;
            padding: 8px;
        }
        .icon-toolbar-btn {
            width: 60px;
            min-height: 60px;
            padding: 8px 6px;
        }
        .icon-toolbar-btn i {
            font-size: 24px;
        }
        .icon-toolbar-btn .btn-label {
            font-size: 10px;
        }
        .map-sidebar {
            top: 85px;
            height: calc(100% - 85px);
            width: 380px;
        }
    }
    
    @media (max-width: 768px) {
        .map-icon-toolbar {
            top: 75px;
            left: 10px;
            padding: 8px 6px;
            gap: 4px;
        }
        .icon-toolbar-btn {
            width: 56px;
            min-height: 56px;
            padding: 8px 4px;
            gap: 4px;
        }
        .icon-toolbar-btn i {
            font-size: 22px;
        }
        .icon-toolbar-btn .btn-label {
            font-size: 9px;
        }
        .icon-toolbar-badge {
            min-width: 16px;
            height: 16px;
            font-size: 9px;
            padding: 0 4px;
        }
        .map-sidebar {
            top: 75px;
            height: calc(100% - 75px);
            width: 100%;
            max-width: 340px;
        }
    }
    
    .map-sidebar.collapsed {
        transform: translateX(calc(100% - 60px));
    }
    
    .sidebar-toggle-btn {
        position: absolute;
        left: -50px;
        top: 50%;
        transform: translateY(-50%);
        width: 50px;
        height: 70px;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 2px solid #e5e7eb;
        border-right: none;
        border-radius: 12px 0 0 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.25s ease;
        z-index: 1001;
        box-shadow: -4px 0 8px rgba(0, 0, 0, 0.08);
    }
    
    .sidebar-toggle-btn:hover {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        box-shadow: -6px 0 12px rgba(0, 0, 0, 0.12);
        border-color: #3b82f6;
    }
    
    .sidebar-toggle-btn i {
        font-size: 28px;
        color: #6b7280;
        transition: transform 0.3s ease;
    }
    
    .sidebar-toggle-btn:hover i {
        color: #3b82f6;
    }
    
    .map-sidebar.collapsed .sidebar-toggle-btn i {
        transform: rotate(180deg);
    }
    
    .sidebar-content {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: linear-gradient(180deg, #ffffff 0%, #fafbfc 100%);
    }
    
    /* Remove old tab styles - now using icon toolbar */
    .sidebar-tabs {
        display: none;
    }
    
    .sidebar-tab {
        display: none;
        margin-bottom: 4px;
    }
    
    .sidebar-tab.active .tab-label {
        color: #111827;
        font-weight: 600;
    }
    
    .sidebar-tab .tab-count {
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        background: #e5e7eb;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 24px;
        text-align: center;
    }
    
    .sidebar-tab.active .tab-count {
        background: #3b82f6;
        color: #ffffff;
    }
    
    .sidebar-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .sidebar-search {
        display: flex;
        align-items: center;
        padding: 12px;
        background: #ffffff;
        border-bottom: 1px solid #e5e7eb;
        gap: 8px;
    }
    
    .sidebar-search .search-icon {
        color: #9ca3af;
        font-size: 20px;
    }
    
    .sidebar-search-input {
        flex: 1;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s ease;
    }
    
    .sidebar-search-input:focus {
        border-color: #3b82f6;
    }
    
    .sidebar-filter-btn {
        background: transparent;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    
    .sidebar-filter-btn:hover {
        background: #f9fafb;
        border-color: #d1d5db;
    }
    
    .sidebar-filter-btn i {
        font-size: 20px;
        color: #6b7280;
    }
    
    .sidebar-list-container {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
    }
    
    .tab-content {
        display: none;
        height: 100%;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .sidebar-list {
        padding: 8px;
    }
    
    .sidebar-list-item {
        display: flex;
        align-items: center;
        padding: 12px;
        margin-bottom: 8px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .sidebar-list-item:hover {
        background: #f9fafb;
        border-color: #d1d5db;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    .sidebar-list-item.active {
        background: #eff6ff;
        border-color: #3b82f6;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15);
    }
    
    /* CCTV specific styles */
    .sidebar-list-item[data-type="cctv"] {
        flex-direction: column;
        align-items: stretch;
        padding: 0;
        overflow: hidden;
        position: relative;
    }
    
    .sidebar-list-item[data-type="cctv"].expanded {
        border-color: #3b82f6;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }
    
    .sidebar-list-item[data-type="cctv"].no-hazard-inspection {
        border-left: 3px solid #ef4444;
    }
    
    .sidebar-list-item[data-type="cctv"].has-hazard-inspection {
        border-left: 3px solid #10b981;
    }
    
    .sidebar-list-item-header {
        display: flex;
        align-items: center;
        padding: 12px;
        transition: background-color 0.2s ease;
    }
    
    .sidebar-list-item[data-type="cctv"]:hover .sidebar-list-item-header {
        background-color: transparent;
    }
    
    .list-item-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 16px;
        color: #ffffff;
        margin-right: 12px;
        flex-shrink: 0;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    /* Pastikan avatar tidak terpengaruh oleh styling CCTV */
    .sidebar-list-item:not([data-type="cctv"]) .list-item-avatar {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .list-item-content {
        flex: 1;
        min-width: 0;
    }
    
    /* Pastikan content tidak terpengaruh oleh styling CCTV */
    .sidebar-list-item:not([data-type="cctv"]) .list-item-content {
        flex: 1;
        min-width: 0;
    }
    
    .list-item-title {
        font-size: 14px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 4px;
        word-break: break-word;
        line-height: 1.4;
    }
    
    .list-item-subtitle {
        font-size: 12px;
        color: #6b7280;
        word-break: break-word;
        line-height: 1.4;
    }
    
    .list-item-time {
        font-size: 11px;
        color: #9ca3af;
        margin-top: 4px;
    }
    
    .list-item-expand-icon {
        margin-left: auto;
        color: #6b7280;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.2s ease, color 0.2s ease;
        flex-shrink: 0;
        font-size: 20px;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
    }
    
    /* Control Room specific styles */
    .sidebar-list-item[data-type="controlroom"] {
        flex-direction: column;
        align-items: stretch;
        padding: 0;
        overflow: hidden;
        position: relative;
        border-radius: 8px;
        margin-bottom: 8px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }
    
    .sidebar-list-item[data-type="controlroom"]:hover {
        border-color: #d1d5db;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    
    .sidebar-list-item[data-type="controlroom"].expanded {
        border-color: #3b82f6;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
    }
    
    .sidebar-list-item[data-type="controlroom"] .sidebar-list-item-header {
        padding: 14px 16px;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    
    .sidebar-list-item[data-type="controlroom"] .sidebar-list-item-header:hover {
        background-color: #f9fafb;
    }
    
    .sidebar-list-item[data-type="controlroom"].expanded .sidebar-list-item-header {
        background-color: #f0f9ff;
        border-bottom: 1px solid #e0f2fe;
    }
    
    .sidebar-list-item[data-type="controlroom"]:hover .list-item-expand-icon {
        background-color: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }
    
    .sidebar-list-item[data-type="controlroom"].expanded .list-item-expand-icon {
        transform: rotate(180deg);
        background-color: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
    }
    
    .controlroom-cctv-list {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), padding 0.3s ease;
        background-color: #f9fafb;
        border-top: 1px solid #e5e7eb;
    }
    
    .sidebar-list-item[data-type="controlroom"].expanded .controlroom-cctv-list {
        max-height: 600px;
        padding: 8px 0;
        overflow-y: auto;
    }
    
    .controlroom-cctv-item {
        padding: 10px 16px 10px 48px;
        border-bottom: 1px solid #e5e7eb;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        background-color: transparent;
    }
    
    .controlroom-cctv-item:last-child {
        border-bottom: none;
    }
    
    .controlroom-cctv-item:hover {
        background-color: #ffffff;
        padding-left: 52px;
    }
    
    .controlroom-cctv-item.active {
        background-color: #e0f2fe;
        border-left: 3px solid #3b82f6;
        padding-left: 49px;
    }
    
    .controlroom-cctv-item::before {
        content: '';
        position: absolute;
        left: 20px;
        top: 50%;
        transform: translateY(-50%);
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: #d1d5db;
        transition: all 0.2s ease;
    }
    
    .controlroom-cctv-item:hover::before {
        background-color: #3b82f6;
        width: 8px;
        height: 8px;
    }
    
    .controlroom-cctv-item.active::before {
        background-color: #3b82f6;
        width: 8px;
        height: 8px;
    }
    
    /* Expand icon hanya untuk CCTV */
    .sidebar-list-item[data-type="cctv"]:hover .list-item-expand-icon {
        background-color: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }
    
    .sidebar-list-item[data-type="cctv"].expanded .list-item-expand-icon {
        transform: rotate(180deg);
        background-color: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
    }
    
    .cctv-hazard-status-icon {
        margin-left: 8px;
        margin-right: 8px;
        flex-shrink: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 14px;
        transition: all 0.2s ease;
    }
    
    .cctv-hazard-status-icon.has-hazard {
        background-color: rgba(16, 185, 129, 0.15);
        color: #10b981;
    }
    
    .cctv-hazard-status-icon.no-hazard {
        background-color: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        animation: pulse-red 2s ease-in-out infinite;
    }
    
    @keyframes pulse-red {
        0%, 100% {
            opacity: 1;
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
        }
        50% {
            opacity: 0.8;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0);
        }
    }
    
    .cctv-hazard-status-icon.loading {
        background-color: rgba(156, 163, 175, 0.15);
        color: #9ca3af;
    }
    
    .cctv-hazard-status-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 10px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .cctv-hazard-status-badge.no-hazard {
        background-color: #fee2e2;
        color: #991b1b;
    }
    
    .cctv-hazard-status-badge.has-hazard {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .cctv-detail-section {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), padding 0.3s ease;
        padding: 0 12px;
        background: #fafbfc;
        border-top: 1px solid #e5e7eb;
    }
    
    .sidebar-list-item.expanded .cctv-detail-section {
        max-height: 2000px;
        padding: 16px 12px;
    }
    
    .cctv-detail-loading {
        text-align: center;
        padding: 24px 20px;
        color: #6b7280;
        font-size: 12px;
    }
    
    .cctv-detail-loading i {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .cctv-detail-error {
        padding: 12px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 6px;
        color: #991b1b;
        font-size: 12px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .cctv-detail-group {
        margin-bottom: 20px;
    }
    
    .cctv-detail-group:last-child {
        margin-bottom: 0;
    }
    
    .cctv-detail-group-title {
        font-size: 13px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
        padding-bottom: 6px;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .cctv-detail-group-title i {
        font-size: 18px;
        color: #3b82f6;
    }
    
    .cctv-coverage-item {
        padding: 10px 12px;
        background: #ffffff;
        border-radius: 6px;
        margin-bottom: 8px;
        border-left: 4px solid #3b82f6;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .cctv-coverage-item:hover {
        transform: translateX(2px);
        box-shadow: 0 2px 6px rgba(59, 130, 246, 0.15);
    }
    
    .cctv-coverage-item:last-child {
        margin-bottom: 0;
    }
    
    .cctv-coverage-lokasi {
        font-size: 12px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .cctv-coverage-lokasi::before {
        content: '📍';
        font-size: 14px;
    }
    
    .cctv-coverage-detail {
        font-size: 11px;
        color: #6b7280;
        margin-left: 20px;
        line-height: 1.5;
    }
    
    .cctv-hazard-stat {
        padding: 10px 12px;
        background: #ffffff;
        border-radius: 6px;
        margin-bottom: 8px;
        border-left: 4px solid #f59e0b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .cctv-hazard-stat:hover {
        transform: translateX(2px);
        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.15);
    }
    
    .cctv-hazard-stat:last-child {
        margin-bottom: 0;
    }
    
    .cctv-hazard-stat-header {
        font-size: 12px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .cctv-hazard-stat-header::before {
        content: '⚠️';
        font-size: 14px;
    }
    
    .cctv-hazard-stat-count {
        font-size: 11px;
        color: #92400e;
        margin-top: 4px;
        padding: 4px 8px;
        background: #fef3c7;
        border-radius: 4px;
        display: inline-block;
        font-weight: 600;
    }
    
    .cctv-pja-item {
        padding: 10px 12px;
        background: #ffffff;
        border-radius: 6px;
        margin-bottom: 8px;
        border-left: 4px solid #10b981;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .cctv-pja-item:hover {
        transform: translateX(2px);
        box-shadow: 0 2px 6px rgba(16, 185, 129, 0.15);
    }
    
    .cctv-pja-item:last-child {
        margin-bottom: 0;
    }
    
    .cctv-pja-name {
        font-size: 12px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .cctv-pja-name::before {
        content: '👤';
        font-size: 14px;
    }
    
    .cctv-pja-info {
        font-size: 11px;
        color: #065f46;
        margin-left: 20px;
        line-height: 1.6;
    }
    
    .cctv-no-data {
        padding: 16px;
        text-align: center;
        color: #9ca3af;
        font-size: 12px;
        font-style: italic;
        background: #f9fafb;
        border-radius: 6px;
        border: 1px dashed #d1d5db;
    }
    
    .empty-state {
        padding: 40px 20px;
        text-align: center;
        color: #9ca3af;
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 12px;
        opacity: 0.5;
    }
    
    .empty-state p {
        font-size: 14px;
        margin: 0;
    }
    
    /* Collapsed state styles */
    .map-sidebar.collapsed {
        width: 50px;
    }
    
    .map-sidebar.collapsed .sidebar-content {
        width: 50px;
    }
    
    .map-sidebar.collapsed .sidebar-tabs {
        flex-direction: column;
        padding: 8px 4px;
        gap: 4px;
        overflow-x: hidden;
        overflow-y: auto;
    }
    
    .map-sidebar.collapsed .sidebar-tab {
        min-height: 50px;
        padding: 8px 4px;
        width: 100%;
        min-width: auto;
        max-width: none;
    }
    
    .map-sidebar.collapsed .sidebar-tab .tab-label,
    .map-sidebar.collapsed .sidebar-tab .tab-count {
        display: none;
    }
    
    .map-sidebar.collapsed .sidebar-tab i {
        margin-bottom: 0;
    }
    
    .map-sidebar.collapsed .sidebar-body {
        display: none;
    }
    
    .map-sidebar.collapsed .sidebar-toggle-btn {
        left: -40px;
    }
    
    .hazard-detection-header {
        margin-bottom: 24px;
    }

    .hazard-detection-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .hazard-detection-subtitle {
        font-size: 14px;
        color: #6b7280;
    }

    /* Detail Total CCTV modal styles */
    #totalCctvModal .modal-content {
        background-color: #F8FAFC;
    }

    #totalCctvModal .modal-body {
        background-color: #F8FAFC;
    }

    #totalCctvModal .card {
        background-color: #ffffff;
        border: none;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
    }

    #totalCctvModal .card .card-body {
        background-color: #ffffff;
    }

    /* Custom column for 5 items */
    @media (min-width: 992px) {
        .col-lg-2-4 {
            flex: 0 0 auto;
            width: 20%;
        }
    }

    .stats-card {
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .stats-card.total {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .stats-card.active {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }

    .stats-card.resolved {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
    }

    .stats-card.critical {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        color: white;
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

    /* Animation untuk angka yang berubah */
    #modalJumlahAreaKritis,
    #modalCctvAreaKritis,
    #modalCctvAreaNonKritis {
        transition: transform 0.3s ease, color 0.3s ease;
    }

    #modalJumlahAreaKritis.animating,
    #modalCctvAreaKritis.animating,
    #modalCctvAreaNonKritis.animating {
        transform: scale(1.1);
        color: #3b82f6;
    }

    /* Animation untuk badge persentase */
    #detailPersentaseCctvAreaKritis,
    #detailPersentaseCctvAreaNonKritis {
        transition: transform 0.2s ease;
    }

    /* Popup style untuk OpenLayers - diperlukan untuk map */
    .ol-popup {
        position: absolute;
        background-color: white;
        box-shadow: 0 1px 4px rgba(0,0,0,0.2);
        padding: 15px;
        border-radius: 10px;
        border: 1px solid #cccccc;
        bottom: 12px;
        left: -50px;
        min-width: 200px;
    }

    .ol-popup:after, .ol-popup:before {
        top: 100%;
        border: solid transparent;
        content: " ";
        height: 0;
        width: 0;
        position: absolute;
        pointer-events: none;
    }

    .ol-popup:after {
        border-top-color: white;
        border-width: 10px;
        left: 48px;
        margin-left: -10px;
    }

    .ol-popup:before {
        border-top-color: #cccccc;
        border-width: 11px;
        left: 48px;
        margin-left: -11px;
    }

    .ol-popup-closer {
        text-decoration: none;
        position: absolute;
        top: 2px;
        right: 8px;
        color: #333;
        font-size: 18px;
        font-weight: bold;
    }

    .ol-popup-closer:hover {
        color: #000;
    }
    
    /* Full-screen map container */
    .map-fullscreen-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 999;
        background-color: #f9fafb;
        overflow: hidden;
    }
    
    /* Map container - full screen */
    #hazardMap {
        width: 100%;
        height: 100%;
        border-radius: 0;
        overflow: hidden;
        box-shadow: none;
        border: none;
        background-color: #f9fafb;
    }
    
    /* Map controls overlay panel */
    .map-controls-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1001;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 0;
        border-bottom-left-radius: 12px;
        border-bottom-right-radius: 12px;
        padding: 16px 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border: none;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    /* Ensure dropdowns appear above map */
    .map-controls-overlay .dropdown-menu {
        z-index: 1002;
    }
    
    .map-controls-overlay .d-flex {
        margin-bottom: 0;
    }
    
    /* Responsive map controls */
    @media (max-width: 1200px) {
        .map-controls-overlay {
            top: 0;
            left: 0;
            right: 0;
            padding: 12px 16px;
        }
    }
    
    @media (max-width: 768px) {
        .map-controls-overlay {
            top: 0;
            left: 0;
            right: 0;
            padding: 10px 12px;
        }
        
        .map-controls-overlay .btn-filter {
            font-size: 12px;
            padding: 6px 10px;
        }
        
        .map-controls-overlay .btn-filter span {
            display: none;
        }
        
        .map-controls-overlay h5 {
            font-size: 16px;
        }
    }
    
    /* Hazard item interaction - menggunakan class Bootstrap */
    .hazard-item {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .hazard-item:hover {
        background-color: #f9fafb;
    }
    
    .hazard-item.selected {
        background-color: #eff6ff;
        border-left: 3px solid #3b82f6;
        padding-left: 12px;
    }

    .hazard-card {
        border-radius: 16px;
        border: 1px solid #e5e7eb;
        padding: 18px;
        background: linear-gradient(180deg, #ffffff 0%, #f9fafb 100%);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        position: relative;
        overflow: hidden;
    }

    .hazard-card::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 18px;
        padding: 1px;
        background: linear-gradient(135deg, rgba(14,165,233,.4), rgba(59,130,246,.2));
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        pointer-events: none;
    }

    .hazard-card-critical::after {
        background: linear-gradient(135deg, rgba(239,68,68,.5), rgba(249,115,22,.4));
    }

    .hazard-card-high::after {
        background: linear-gradient(135deg, rgba(249,115,22,.45), rgba(251,191,36,.35));
    }

    .hazard-card-medium::after {
        background: linear-gradient(135deg, rgba(59,130,246,.4), rgba(14,165,233,.3));
    }

    .hazard-card-low::after {
        background: linear-gradient(135deg, rgba(34,197,94,.45), rgba(16,185,129,.35));
    }

    .hazard-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .hazard-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .hazard-pill-critical {
        background: rgba(239,68,68,.12);
        color: #b91c1c;
    }

    .hazard-pill-high {
        background: rgba(251,191,36,.18);
        color: #b45309;
    }

    .hazard-pill-medium {
        background: rgba(59,130,246,.15);
        color: #1d4ed8;
    }

    .hazard-pill-low {
        background: rgba(16,185,129,.16);
        color: #047857;
    }

    .hazard-card-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 14px;
        margin-top: 12px;
    }

    .hazard-card-meta span {
        display: block;
        font-size: 12px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .hazard-card-meta strong {
        display: block;
        color: #0f172a;
        font-size: 14px;
        margin-top: 4px;
    }

    .hazard-card-footer {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 18px;
    }

    .hazard-chip {
        background: rgba(15,23,42,.04);
        color: #0f172a;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 12px;
    }

    .hazard-card-actions .btn {
        border-radius: 999px;
        padding: 6px 16px;
    }

    /* Area Kritis Card Styles */
    .area-kritis-card {
        transition: all 0.2s ease;
    }

    .area-kritis-card:hover {
        border-color: #d1d5db !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .area-icon-wrapper {
        transition: background-color 0.2s ease;
    }

    .area-kritis-card-1:hover .area-icon-wrapper {
        background: #fee2e2 !important;
    }

    .area-kritis-card-2:hover .area-icon-wrapper {
        background: #ffedd5 !important;
    }

    .area-kritis-card-3:hover .area-icon-wrapper {
        background: #dcfce7 !important;
    }

    .luasan-info-card {
        border-radius: 14px;
        border: 1px solid rgba(59, 130, 246, 0.15);
        background: rgba(219, 234, 254, 0.6);
        padding: 14px;
    }
    
    /* CCTV Icon Style */
    .cctv-icon-marker {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-radius: 50% 50% 50% 0;
        transform: rotate(-45deg);
        border: 3px solid #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .cctv-icon-marker:hover {
        transform: rotate(-45deg) scale(1.1);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.5);
    }
    
    .cctv-icon-marker::before {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        background: #ffffff;
        border-radius: 50%;
        transform: rotate(45deg);
    }
    
    .cctv-icon-marker::after {
        content: '📹';
        position: absolute;
        transform: rotate(45deg);
        font-size: 16px;
        z-index: 1;
    }

    /* Notification Styles */
    .notification-container {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-width: 400px;
    }

    .notification-item {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        padding: 16px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        animation: slideInRight 0.3s ease-out;
        border-left: 4px solid #ef4444;
        position: relative;
        overflow: hidden;
    }

    .notification-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #ef4444, #f97316);
        animation: progressBar 5s linear forwards;
    }

    .notification-item.success {
        border-left-color: #10b981;
    }

    .notification-item.success::before {
        background: linear-gradient(90deg, #10b981, #34d399);
    }

    .notification-item.warning {
        border-left-color: #f59e0b;
    }

    .notification-item.warning::before {
        background: linear-gradient(90deg, #f59e0b, #fbbf24);
    }

    .notification-item.info {
        border-left-color: #3b82f6;
    }

    .notification-item.info::before {
        background: linear-gradient(90deg, #3b82f6, #60a5fa);
    }

    .notification-icon {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .notification-item.success .notification-icon {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }

    .notification-item.warning .notification-icon {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }

    .notification-item.info .notification-icon {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }

    .notification-content {
        flex: 1;
        min-width: 0;
    }

    .notification-title {
        font-weight: 600;
        font-size: 14px;
        color: #111827;
        margin-bottom: 4px;
    }

    .notification-message {
        font-size: 13px;
        color: #6b7280;
        line-height: 1.4;
    }

    .notification-time {
        font-size: 11px;
        color: #9ca3af;
        margin-top: 4px;
    }

    .notification-close {
        position: absolute;
        top: 8px;
        right: 8px;
        background: transparent;
        border: none;
        color: #9ca3af;
        cursor: pointer;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .notification-close:hover {
        background: rgba(0, 0, 0, 0.05);
        color: #111827;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    @keyframes progressBar {
        from {
            width: 100%;
        }
        to {
            width: 0%;
        }
    }

    .notification-item.hiding {
        animation: slideOutRight 0.3s ease-out forwards;
    }
    
    .cctv-icon-marker.live::before {
        background: #10b981;
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
    
    /* Animation for Total CCTV Count */
    #totalCctvCountDynamic {
        transition: all 0.3s ease;
    }
    
    #totalCctvCountDynamic.updating {
        opacity: 0.6;
        transform: scale(0.95);
    }
    
    #totalCctvCountDynamic.pulse-animation {
        animation: pulseCctv 0.6s ease-in-out;
    }
    
    @keyframes pulseCctv {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
            color: #10b981;
        }
        100% {
            transform: scale(1);
        }
    }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@8.2.0/ol.css">
<link rel="stylesheet" href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}">
<!-- TourGuide JS CSS -->
<link rel="stylesheet" href="https://unpkg.com/@sjmc11/tourguidejs/dist/css/tour.min.css">
<style>
    /* Custom TourGuide Dialog Styles */
    [data-tg-dialog] {
        max-width: 380px !important;
        width: 100% !important;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3) !important;
        border-radius: 12px !important;
        overflow: hidden !important;
    }
    
    [data-tg-dialog-body] {
        padding: 20px !important;
        max-height: 400px;
        overflow-y: auto;
    }
    
    [data-tg-dialog-footer] {
        padding: 12px 20px !important;
        border-top: 1px solid #e5e7eb !important;
        background-color: #f9fafb !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 10px !important;
    }
    
    [data-tg-step-dots] {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        gap: 8px !important;
        margin-bottom: 8px !important;
        padding: 8px 0 !important;
        flex-wrap: wrap !important;
    }
    
    [data-tg-step-dot] {
        width: 8px !important;
        height: 8px !important;
        border-radius: 50% !important;
        background-color: #d1d5db !important;
        border: none !important;
        padding: 0 !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        margin: 0 2px !important;
    }
    
    [data-tg-step-dot][data-tg-active="true"] {
        background-color: #3b82f6 !important;
        width: 24px !important;
        border-radius: 4px !important;
    }
    
    [data-tg-dialog-footer] [data-tg-buttons] {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        gap: 10px !important;
        width: 100% !important;
    }
    
    [data-tg-button] {
        flex: 1 !important;
        padding: 10px 16px !important;
        border-radius: 8px !important;
        font-weight: 500 !important;
        font-size: 14px !important;
        border: 1px solid #e5e7eb !important;
        transition: all 0.2s ease !important;
        cursor: pointer !important;
        background-color: white !important;
        color: #374151 !important;
    }
    
    [data-tg-button][data-tg-button-primary] {
        background-color: #3b82f6 !important;
        color: white !important;
        border-color: #3b82f6 !important;
    }
    
    [data-tg-button][data-tg-button-primary]:hover {
        background-color: #2563eb !important;
        border-color: #2563eb !important;
    }
    
    [data-tg-button]:not([data-tg-button-primary]):hover {
        background-color: #f9fafb !important;
    }
    
    [data-tg-step-progress] {
        font-size: 12px !important;
        color: #6b7280 !important;
        margin-bottom: 8px !important;
        text-align: center !important;
        width: 100% !important;
    }
    
    /* Alternative selectors for TourGuide JS */
    .tg-dialog,
    [class*="tg-dialog"] {
        max-width: 380px !important;
        width: 100% !important;
    }
    
    .tg-dialog-footer,
    [class*="tg-dialog-footer"] {
        padding: 12px 20px !important;
        border-top: 1px solid #e5e7eb !important;
        background-color: #f9fafb !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 10px !important;
    }
    
    .tg-step-dots,
    [class*="tg-step-dots"] {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        gap: 8px !important;
        margin-bottom: 8px !important;
        padding: 8px 0 !important;
        flex-wrap: wrap !important;
    }
    
    .tg-buttons,
    [class*="tg-buttons"] {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        gap: 10px !important;
        width: 100% !important;
    }
    
    .tg-button,
    [class*="tg-button"] {
        flex: 1 !important;
        padding: 10px 16px !important;
        border-radius: 8px !important;
        font-weight: 500 !important;
        font-size: 14px !important;
        border: 1px solid #e5e7eb !important;
        transition: all 0.2s ease !important;
        cursor: pointer !important;
    }
</style>
@endsection

@section('content')
<div class="hazard-detection-header mt-5" >
    <h1 class="hazard-detection-title">Hazard in Motion </h1>
    <p class="hazard-detection-subtitle">Real-time detection and monitoring of safety hazards in operational areas</p>
    
    <!-- Collapse Header Button -->
    <div class="mb-3" data-tg-tour="<strong>Dashboard Statistics & Coverage</strong><br><br>Klik untuk membuka/menutup panel statistik dashboard. Panel ini menampilkan ringkasan data CCTV, coverage area, dan statistik area kritis.">
        <button class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-between p-3 rounded-4 shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#dashboardStatsCollapse" aria-expanded="true" aria-controls="dashboardStatsCollapse">
            <span class="fw-bold d-flex align-items-center">
                <i class="material-icons-outlined me-2">dashboard</i>
                Dashboard Statistics & Coverage
            </span>
            <i class="material-icons-outlined collapse-icon">expand_less</i>
        </button>
    </div>

    <!-- Collapsible Content -->
    <div class="collapse show" id="dashboardStatsCollapse">
    <div class="row">
          <div class="col-12 d-flex">
            <div class="card rounded-4 w-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3" data-tg-tour="<strong>Luasan Coverage CCTV</strong><br><br>Menampilkan statistik coverage CCTV meliputi:<br>• Total CCTV: Jumlah unit kamera CCTV<br>• Luasan Area Kerja: Total area yang dikerjakan<br>• Luasan CCTV: Total area yang tercover CCTV<br>• Coverage: Persentase coverage CCTV terhadap area kerja">
                  <h5 class="mb-0 fw-bold">Luasan Coverage CCTV</h5>
                  <span class="badge bg-primary" id="coverageBadge">0% Coverage</span>
                </div>
                <div class="d-flex align-items-center justify-content-around flex-wrap gap-4 p-4">
                     
                  <button type="button" class="btn p-0 border-0 bg-transparent d-flex flex-column align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#totalCctvModal" title="Lihat detail Total CCTV" data-tg-tour="<strong>Total CCTV</strong><br><br>Klik untuk melihat detail total jumlah kamera CCTV yang terpasang di semua area kerja.">
                    <span class="mb-2 wh-48 bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">view_list</i>
                    </span>
                    <h3 class="mb-0" id="totalCctvCountDynamic">{{ number_format($totalCctvCount ?? 0) }}</h3>
                    <p class="mb-0">Total CCTV</p>
                    <small class="text-muted" id="totalCctvLabel">unit</small>
                  </button>
                  <div class="vr"></div>
                  <button type="button" class="btn p-0 border-0 bg-transparent d-flex flex-column align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#totalCctvModal" title="Lihat detail Luasan Area Kerja" data-tg-tour="<strong>Luasan Area Kerja</strong><br><br>Menampilkan total luas area kerja yang dimonitor. Klik untuk melihat detail lebih lanjut.">
                    <span class="mb-2 wh-48 bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">square_foot</i>
                    </span>
                    <h3 class="mb-0" id="luasanAreaKerja">0</h3>
                    <p class="mb-0">Luasan Area Kerja</p>
                    <small class="text-muted" id="luasanAreaKerjaUnit">m²</small>
                  </button>
                  <div class="vr"></div>
                  <button type="button" class="btn p-0 border-0 bg-transparent d-flex flex-column align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#cctvOnModal" title="Lihat detail Luasan CCTV" data-tg-tour="<strong>Luasan CCTV</strong><br><br>Menampilkan total luas area yang tercover oleh kamera CCTV. Klik untuk melihat detail lebih lanjut.">
                    <span class="mb-2 wh-48 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">videocam</i>
                    </span>
                    <h3 class="mb-0" id="luasanCctv">0</h3>
                    <p class="mb-0">Luasan CCTV</p>
                    <small class="text-muted" id="luasanCctvUnit">m²</small>
                  </button>
                 
                  <div class="vr"></div>
                  <button type="button" class="btn p-0 border-0 bg-transparent d-flex flex-column align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#criticalCctvModal" title="Lihat detail Coverage" data-tg-tour="<strong>Coverage Percentage</strong><br><br>Menampilkan persentase coverage CCTV terhadap total area kerja. Semakin tinggi persentase, semakin baik monitoring area kerja.">
                    <span class="mb-2 wh-48 bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">percent</i>
                    </span>
                    <h3 class="mb-0" id="coveragePercentage">0%</h3>
                    <p class="mb-0">Coverage</p>
                    <small class="text-muted" id="coverageLabel">persentase</small>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

    <div class="row">
    
          <div class="col-12 col-xl-5 col-xxl-4 d-flex">
            <div class="card rounded-4 w-100 shadow-none bg-transparent border-0">
               <div class="card-body p-0">
                 <div class="row g-4">
                    

              
                    <div class="col-12 col-xl-12">
                        <div class="card mb-4 border-0 shadow-sm" data-tg-tour="<strong>Overview Area Kritis</strong><br><br>Menampilkan statistik area kritis berdasarkan kategori:<br>• Jumlah Area Kritis: Total area dengan potensi bahaya tinggi<br>• CCTV Area Kritis: Jumlah CCTV di area kritis<br>• CCTV Area Non Kritis: Jumlah CCTV di area non kritis<br><br>Klik setiap item untuk melihat detail lebih lanjut.">
                            <div class="card-body p-4">
                                <div class="mb-3">
                                    <h5 class="fw-bold mb-1">Overview Area Kritis</h5>
                                    <small class="text-muted">Statistik area kritis berdasarkan kategori</small>
                                </div>
                                
                                <div class="accordion" id="accordionAreaKritis">
                                    <!-- Jumlah Area Kritis -->
                                    <div class="accordion-item mb-3">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseJumlahAreaKritis">
                                                <div class="d-flex align-items-center gap-3 w-100">
                                                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                                                        <span class="material-icons-outlined text-danger">warning</span>
                                                    </div>
                                                    <div>
                                                        <h4 class="mb-0 fw-bold" id="modalJumlahAreaKritis">0</h4>
                                                        <small class="text-muted">Jumlah Area Kritis</small>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="collapseJumlahAreaKritis" class="accordion-collapse collapse" data-bs-parent="#accordionAreaKritis">
                                            <div class="accordion-body">
                                                <div class="mb-3">
                                                    <strong>Penjelasan:</strong> Menampilkan total jumlah area yang dikategorikan sebagai area kritis. 
                                                    Area kritis adalah lokasi yang memiliki potensi bahaya tinggi dan memerlukan monitoring khusus 
                                                    untuk mencegah terjadinya insiden keselamatan kerja.
                                                </div>
                                                <div class="row g-3 mt-2">
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Total Area Kritis:</span>
                                                            <span class="badge bg-danger fs-6" id="detailJumlahAreaKritis">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Total Area Non Kritis:</span>
                                                            <span class="badge bg-success fs-6" id="detailJumlahAreaNonKritis">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Total Area:</span>
                                                            <span class="badge bg-primary fs-6" id="detailTotalArea">0</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mt-3" id="listDetailAreaKritis">
                                                    <strong>Detail Area Kritis:</strong>
                                                    <div class="mt-2">
                                                        <small class="text-muted">Memuat data...</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- CCTV Area Kritis -->
                                    <div class="accordion-item mb-3">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCctvAreaKritis">
                                                <div class="d-flex align-items-center gap-3 w-100">
                                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                                        <span class="material-icons-outlined text-warning">videocam</span>
                                                    </div>
                                                    <div>
                                                        <h4 class="mb-0 fw-bold" id="modalCctvAreaKritis">0</h4>
                                                        <small class="text-muted">CCTV Area Kritis</small>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="collapseCctvAreaKritis" class="accordion-collapse collapse" data-bs-parent="#accordionAreaKritis">
                                            <div class="accordion-body">
                                                <div class="mb-3">
                                                    <strong>Penjelasan:</strong> Menampilkan jumlah kamera CCTV yang terpasang di area kritis. 
                                                    CCTV ini berfungsi untuk memantau aktivitas di area berbahaya secara real-time dan membantu 
                                                    dalam deteksi dini potensi bahaya atau insiden keselamatan.
                                                </div>
                                                <div class="row g-3 mt-2">
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">CCTV di Area Kritis:</span>
                                                            <span class="badge bg-warning fs-6" id="detailCctvAreaKritis">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Total CCTV:</span>
                                                            <span class="badge bg-primary fs-6" id="detailTotalCctvAreaKritis">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Persentase Coverage Area Kritis:</span>
                                                            <span class="badge bg-info fs-6" id="detailPersentaseCctvAreaKritis">0%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- CCTV Area Non Kritis -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCctvAreaNonKritis">
                                                <div class="d-flex align-items-center gap-3 w-100">
                                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                                        <span class="material-icons-outlined text-success">check_circle</span>
                                                    </div>
                                                    <div>
                                                        <h4 class="mb-0 fw-bold" id="modalCctvAreaNonKritis">0</h4>
                                                        <small class="text-muted">CCTV Area Non Kritis</small>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="collapseCctvAreaNonKritis" class="accordion-collapse collapse" data-bs-parent="#accordionAreaKritis">
                                            <div class="accordion-body">
                                                <div class="mb-3">
                                                    <strong>Penjelasan:</strong> Menampilkan jumlah kamera CCTV yang terpasang di area non kritis. 
                                                    Area non kritis adalah lokasi dengan tingkat risiko rendah yang tetap dipantau untuk 
                                                    keamanan umum dan operasional sehari-hari.
                                                </div>
                                                <div class="row g-3 mt-2">
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">CCTV di Area Non Kritis:</span>
                                                            <span class="badge bg-success fs-6" id="detailCctvAreaNonKritis">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Total CCTV:</span>
                                                            <span class="badge bg-primary fs-6" id="detailTotalCctvAreaNonKritis">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Persentase Coverage Area Non Kritis:</span>
                                                            <span class="badge bg-info fs-6" id="detailPersentaseCctvAreaNonKritis">0%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                   </div>
                 
                   <!-- <div class="col-12 col-xl-12">
                    <div class="card mb-4 rounded-4 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start justify-content-between mb-4">
                                <div class="">
                                    <h5 class="mb-1 fw-bold">Overview Area Kritis</h5>
                                    <small class="text-muted">Statistik area kritis berdasarkan kategori_area_tercapture</small>
                                </div>
                            </div>
                            <div class="accordion" id="accordionAreaKritis">
                                <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-3 g-4">
                                    <div class="col">
                                        <div class="accordion-item rounded-3 h-100 bg-white" style="border: 1px solid #e5e7eb;">
                                            <h2 class="accordion-header" id="headingJumlahAreaKritis">
                                                <button class="accordion-button collapsed p-4" type="button" data-bs-toggle="collapse" data-bs-target="#collapseJumlahAreaKritis" aria-expanded="false" aria-controls="collapseJumlahAreaKritis">
                                                    <div class="d-flex align-items-center gap-3 w-100">
                                                        <div class="area-icon-wrapper flex-shrink-0" style="width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; background: #fef2f2; border-radius: 12px;">
                                                            <span class="material-icons-outlined" style="font-size: 28px; color: #ef4444;">warning</span>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h3 class="mb-1 fw-bold text-dark" id="modalJumlahAreaKritis" style="font-size: 28px; line-height: 1.2;">0</h3>
                                                            <p class="mb-0 text-muted" style="font-size: 13px;">Jumlah Area Kritis</p>
                                                        </div>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="collapseJumlahAreaKritis" class="accordion-collapse collapse" aria-labelledby="headingJumlahAreaKritis" data-bs-parent="#accordionAreaKritis">
                                                <div class="accordion-body">
                                                    <p class="mb-0" style="font-size: 13px;">
                                                        <strong>Penjelasan:</strong> Menampilkan total jumlah area yang dikategorikan sebagai area kritis. 
                                                        Area kritis adalah lokasi yang memiliki potensi bahaya tinggi dan memerlukan monitoring khusus 
                                                        untuk mencegah terjadinya insiden keselamatan kerja.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="accordion-item rounded-3 h-100 bg-white" style="border: 1px solid #e5e7eb;">
                                            <h2 class="accordion-header" id="headingCctvAreaKritis">
                                                <button class="accordion-button collapsed p-4" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCctvAreaKritis" aria-expanded="false" aria-controls="collapseCctvAreaKritis">
                                                    <div class="d-flex align-items-center gap-3 w-100">
                                                        <div class="area-icon-wrapper flex-shrink-0" style="width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; background: #fff7ed; border-radius: 12px;">
                                                            <span class="material-icons-outlined" style="font-size: 28px; color: #f97316;">videocam</span>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h3 class="mb-1 fw-bold text-dark" id="modalCctvAreaKritis" style="font-size: 28px; line-height: 1.2;">0</h3>
                                                            <p class="mb-0 text-muted" style="font-size: 13px;">CCTV Area Kritis</p>
                                                        </div>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="collapseCctvAreaKritis" class="accordion-collapse collapse" aria-labelledby="headingCctvAreaKritis" data-bs-parent="#accordionAreaKritis">
                                                <div class="accordion-body">
                                                    <p class="mb-0" style="font-size: 13px;">
                                                        <strong>Penjelasan:</strong> Menampilkan jumlah kamera CCTV yang terpasang di area kritis. 
                                                        CCTV ini berfungsi untuk memantau aktivitas di area berbahaya secara real-time dan membantu 
                                                        dalam deteksi dini potensi bahaya atau insiden keselamatan.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="accordion-item rounded-3 h-100 bg-white" style="border: 1px solid #e5e7eb;">
                                            <h2 class="accordion-header" id="headingCctvAreaNonKritis">
                                                <button class="accordion-button collapsed p-4" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCctvAreaNonKritis" aria-expanded="false" aria-controls="collapseCctvAreaNonKritis">
                                                    <div class="d-flex align-items-center gap-3 w-100">
                                                        <div class="area-icon-wrapper flex-shrink-0" style="width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; background: #f0fdf4; border-radius: 12px;">
                                                            <span class="material-icons-outlined" style="font-size: 28px; color: #22c55e;">check_circle</span>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h3 class="mb-1 fw-bold text-dark" id="modalCctvAreaNonKritis" style="font-size: 28px; line-height: 1.2;">0</h3>
                                                            <p class="mb-0 text-muted" style="font-size: 13px;">CCTV Area Non Kritis</p>
                                                        </div>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="collapseCctvAreaNonKritis" class="accordion-collapse collapse" aria-labelledby="headingCctvAreaNonKritis" data-bs-parent="#accordionAreaKritis">
                                                <div class="accordion-body">
                                                    <p class="mb-0" style="font-size: 13px;">
                                                        <strong>Penjelasan:</strong> Menampilkan jumlah kamera CCTV yang terpasang di area non kritis. 
                                                        Area non kritis adalah lokasi dengan tingkat risiko rendah yang tetap dipantau untuk 
                                                        keamanan umum dan operasional sehari-hari.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                   </div> -->
                   {{-- <div class="col-12 col-xl-12">
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="mb-3">
                                    <h5 class="fw-bold mb-1">Overview Area Kritis</h5>
                                    <small class="text-muted">Statistik area kritis berdasarkan kategori</small>
                                </div>
                                
                                <div class="accordion" id="accordionAreaKritis">
                                    <!-- Jumlah Area Kritis -->
                                    <div class="accordion-item mb-3">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseJumlahAreaKritis">
                                                <div class="d-flex align-items-center gap-3 w-100">
                                                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                                                        <span class="material-icons-outlined text-danger">warning</span>
                                                    </div>
                                                    <div>
                                                        <h4 class="mb-0 fw-bold" id="modalJumlahAreaKritis">0</h4>
                                                        <small class="text-muted">Jumlah Area Kritis</small>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="collapseJumlahAreaKritis" class="accordion-collapse collapse" data-bs-parent="#accordionAreaKritis">
                                            <div class="accordion-body">
                                                <div class="mb-3">
                                                    <strong>Penjelasan:</strong> Menampilkan total jumlah area yang dikategorikan sebagai area kritis. 
                                                    Area kritis adalah lokasi yang memiliki potensi bahaya tinggi dan memerlukan monitoring khusus 
                                                    untuk mencegah terjadinya insiden keselamatan kerja.
                                                </div>
                                                <div class="row g-3 mt-2">
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Total Area Kritis:</span>
                                                            <span class="badge bg-danger fs-6" id="detailJumlahAreaKritis">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Total Area Non Kritis:</span>
                                                            <span class="badge bg-success fs-6" id="detailJumlahAreaNonKritis">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Total Area:</span>
                                                            <span class="badge bg-primary fs-6" id="detailTotalArea">0</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mt-3" id="listDetailAreaKritis">
                                                    <strong>Detail Area Kritis:</strong>
                                                    <div class="mt-2">
                                                        <small class="text-muted">Memuat data...</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- CCTV Area Kritis -->
                                    <div class="accordion-item mb-3">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCctvAreaKritis">
                                                <div class="d-flex align-items-center gap-3 w-100">
                                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                                        <span class="material-icons-outlined text-warning">videocam</span>
                                                    </div>
                                                    <div>
                                                        <h4 class="mb-0 fw-bold" id="modalCctvAreaKritis">0</h4>
                                                        <small class="text-muted">CCTV Area Kritis</small>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="collapseCctvAreaKritis" class="accordion-collapse collapse" data-bs-parent="#accordionAreaKritis">
                                            <div class="accordion-body">
                                                <div class="mb-3">
                                                    <strong>Penjelasan:</strong> Menampilkan jumlah kamera CCTV yang terpasang di area kritis. 
                                                    CCTV ini berfungsi untuk memantau aktivitas di area berbahaya secara real-time dan membantu 
                                                    dalam deteksi dini potensi bahaya atau insiden keselamatan.
                                                </div>
                                                <div class="row g-3 mt-2">
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">CCTV di Area Kritis:</span>
                                                            <span class="badge bg-warning fs-6" id="detailCctvAreaKritis">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Total CCTV:</span>
                                                            <span class="badge bg-primary fs-6" id="detailTotalCctvAreaKritis">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Persentase Coverage Area Kritis:</span>
                                                            <span class="badge bg-info fs-6" id="detailPersentaseCctvAreaKritis">0%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- CCTV Area Non Kritis -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCctvAreaNonKritis">
                                                <div class="d-flex align-items-center gap-3 w-100">
                                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                                        <span class="material-icons-outlined text-success">check_circle</span>
                                                    </div>
                                                    <div>
                                                        <h4 class="mb-0 fw-bold" id="modalCctvAreaNonKritis">0</h4>
                                                        <small class="text-muted">CCTV Area Non Kritis</small>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="collapseCctvAreaNonKritis" class="accordion-collapse collapse" data-bs-parent="#accordionAreaKritis">
                                            <div class="accordion-body">
                                                <div class="mb-3">
                                                    <strong>Penjelasan:</strong> Menampilkan jumlah kamera CCTV yang terpasang di area non kritis. 
                                                    Area non kritis adalah lokasi dengan tingkat risiko rendah yang tetap dipantau untuk 
                                                    keamanan umum dan operasional sehari-hari.
                                                </div>
                                                <div class="row g-3 mt-2">
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">CCTV di Area Non Kritis:</span>
                                                            <span class="badge bg-success fs-6" id="detailCctvAreaNonKritis">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Total CCTV:</span>
                                                            <span class="badge bg-primary fs-6" id="detailTotalCctvAreaNonKritis">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                                            <span class="fw-semibold">Persentase Coverage Area Non Kritis:</span>
                                                            <span class="badge bg-info fs-6" id="detailPersentaseCctvAreaNonKritis">0%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                   </div> --}}
                   <div class="col-12  d-flex">
                      <div class="card mb-0 rounded-4 w-100" data-tg-tour="<strong>Chart Perusahaan dengan CCTV Terbanyak</strong><br><br>Menampilkan grafik perbandingan jumlah CCTV per perusahaan. Grafik ini membantu melihat distribusi CCTV di berbagai perusahaan kontraktor.">
                       <div class="card-body">
                         <div class="d-flex align-items-start justify-content-between mb-3">
                          <div class="">
                            <h4 class="mb-0">{{ number_format($totalYtdInsiden ?? 9) }}</h4>
                            <p class="mb-0">Perusahaan dengan CCTV Terbanyak</p>
                          </div>
                           <div class="dropdown">
                             <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                               data-bs-toggle="dropdown">
                               <span class="material-icons-outlined fs-5">more_vert</span>
                             </a>
                             <ul class="dropdown-menu">
                               <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                               <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                               <li><a class="dropdown-item" href="javascript:;">Something else here</a></li>
                             </ul>
                           </div>
                         </div>
                         <div class="chart-container2">
                           <div id="chart3"></div>
                         </div>
                         <div class="text-center">
                          <p class="mb-0"><span class="text-success me-1">{{ $ytdInsidenChange ?? '12.5' }}%</span> from last month</p>
                        </div>
                       </div>
                      </div>
                   </div>


                   

                 </div><!--end row-->
               </div>
            </div>  
          </div> 
          <div class="col-12 col-xl-7 col-xxl-8 d-flex">
            <div class="card w-100 rounded-4" data-tg-tour="<strong>Area Kerja & CCTV Coverage</strong><br><br>Tabel ini menampilkan detail coverage CCTV untuk setiap area kerja. Anda dapat melihat:<br>• Nama area kerja<br>• Luasan area<br>• Jumlah CCTV<br>• Persentase coverage<br><br>Di bawah tabel terdapat statistik TBC, HAZARD, INSIDEN, GR, dan kondisi CCTV.">
               <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Area Kerja & CCTV Coverage</h5>
                    <small class="text-muted">Overview area kerja yang tercover CCTV</small>
                  </div>
                  <button class="btn btn-sm btn-primary" id="btnShowDetail" onclick="openCoverageModal()" style="display: none;">
                    <i class="material-icons-outlined me-1" style="font-size: 16px;">visibility</i>Detail
                  </button>
                 </div>
                 <div class="table-responsive">
                   <table class="table table-hover align-middle mb-4" id="coverageTable">
                     <tbody id="coverageTableBody">
                       <tr>
                         <td colspan="5" class="text-center text-muted py-4">
                           <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                           Memuat data...
                         </td>
                       </tr>
                     </tbody>
                   </table>
                 </div>
                  <div class="d-flex flex-column flex-lg-row align-items-start justify-content-around border p-3 rounded-4 mt-3 gap-3" data-tg-tour="<strong>Statistik Utama</strong><br><br>Menampilkan statistik utama:<br>• TBC: Total valid TBC<br>• HAZARD: Jumlah hazard terdeteksi<br>• INSIDEN: Jumlah insiden<br>• GR: Golden Rule violations<br><br>Setiap card menampilkan donut chart dan persentase perubahan.">
                     {{-- <div class="vr"></div> --}}
                    <div class="d-flex align-items-center gap-4 cctv-stat-card" id="cctvStatCard" style="cursor: pointer; padding: 8px; border-radius: 8px; transition: all 0.2s;" 
                         title="Klik untuk melihat detail TBC">
                      <div class="">
                        <p class="mb-0 data-attributes">
                          <span id="donutCctv"
                            data-peity='{ "fill": ["#6f42c1", "rgb(0 0 0 / 10%)"], "innerRadius": 32, "radius": 40 }'>0/100</span>
                        </p>
                      </div>
                      <div class="">
                        <p class="mb-1 fs-6 fw-bold">TBC</p>
                        <h2 class="mb-0" id="statCctvCount">0</h2>
                        <p class="mb-0"><span class="text-success me-2 fw-medium" id="statCctvChange">0 %</span><span id="statCctvText"> valid TBC</span></p>
                      </div>
                    </div>
                     <div class="vr"></div>
                    <div class="d-flex align-items-center gap-4">
                      <div class="">
                        <p class="mb-0 data-attributes">
                          <span id="donutHazard"
                            data-peity='{ "fill": ["#0d6efd", "rgb(0 0 0 / 10%)"], "innerRadius": 32, "radius": 40 }'>0/100</span>
                        </p>
                      </div>
                      <div class="">
                        <p class="mb-1 fs-6 fw-bold">HAZARD</p>
                        <h2 class="mb-0" id="statHazardCount">{{ number_format($monthlyHazards ?? 65) }}</h2>
                        <p class="mb-0"><span class="text-success me-2 fw-medium" id="statHazardChange">{{ $monthlyChange ?? '16.5' }}%</span><span id="statHazardText">{{ $monthlyCount ?? '55' }} hazards</span></p>
                      </div>
                    </div>
                   
                    

                      <div class="vr"></div>
                    <div class="d-flex align-items-center gap-4">
                      <div class="">
                        <p class="mb-0 data-attributes">
                          <span id="donutInsiden"
                            data-peity='{ "fill": ["#fd7e14", "rgb(0 0 0 / 10%)"], "innerRadius": 32, "radius": 40 }'>0/100</span>
                        </p>
                      </div>
                      <div class="">
                        <p class="mb-1 fs-6 fw-bold">INSIDEN</p>
                        <h2 class="mb-0" id="statInsidenCount">{{ number_format($yearlyHazards ?? 9) }}</h2>
                        <p class="mb-0"><span class="text-success me-2 fw-medium" id="statInsidenChange">{{ $yearlyChange ?? '24.9' }}%</span><span id="statInsidenText">{{ $yearlyCount ?? '267' }} hazards</span></p>
                      </div>
                    </div>

                      <div class="vr"></div>
                    <div class="d-flex align-items-center gap-4">
                      <div class="">
                        <p class="mb-0 data-attributes">
                          <span id="donutGr"
                            data-peity='{ "fill": ["#20c997", "rgb(0 0 0 / 10%)"], "innerRadius": 32, "radius": 40 }'>0/100</span>
                        </p>
                      </div>
                      <div class="">
                        <p class="mb-1 fs-6 fw-bold">GR</p>
                        <h2 class="mb-0" id="statGrCount">{{ number_format($validGrCount ?? 0) }}</h2>
                        <p class="mb-0"><span class="text-success me-2 fw-medium" id="statGrChange">{{ $yearlyChange ?? '24.9' }}%</span><span id="statGrText">{{ $yearlyCount ?? '267' }} hazards</span></p>
                      </div>
                    </div>

                    
                  </div>

                 <div class="d-flex flex-column flex-lg-row align-items-start justify-content-around border p-3 rounded-4 mt-3 gap-3" data-tg-tour="<strong>Statistik CCTV</strong><br><br>Menampilkan statistik kondisi CCTV:<br>• CCTV Live View & Connected: CCTV yang aktif dan terhubung<br>• Kondisi CCTV Baik: CCTV yang berfungsi dengan baik<br>• Kondisi CCTV Tidak Baik: CCTV yang bermasalah atau tidak berfungsi<br><br>Gunakan data ini untuk monitoring kesehatan sistem CCTV.">
                   
                   <div class="d-flex align-items-center gap-4 cctv-stat-card" id="cctvStatCard" style="cursor: pointer; padding: 8px; border-radius: 8px; transition: all 0.2s;" 
                        title="Klik untuk melihat detail CCTV">
                     <div class="">
                       <p class="mb-0 data-attributes">
                         <span id="donutCctvAktif"
                           data-peity='{ "fill": ["#6f42c1", "rgb(0 0 0 / 10%)"], "innerRadius": 32, "radius": 40 }'>0/100</span>
                       </p>
                     </div>
                     <div class="">
                       <p class="mb-1 fs-6 fw-bold">CCTV Live View & Connected</p>
                       <h2 class="mb-0" id="statCctvAktifCount">{{ number_format($yearlyHazards ?? 0 ) }}</h2>
                       <p class="mb-0">
                         <span class="text-success me-2 fw-medium" id="statCctvAktifChange">0%</span>
                         <span id="statCctvAktifText">0 CCTV</span>
                       </p>
                     </div>
                   </div>

                      <div class="vr"></div>
                    <div class="d-flex align-items-center gap-4">
                      <div class="">
                        <p class="mb-0 data-attributes">
                          <span id="donutKondisiBaik"
                            data-peity='{ "fill": ["#0d6efd", "rgb(0 0 0 / 10%)"], "innerRadius": 32, "radius": 40 }'>0/100</span>
                        </p>
                      </div>
                      <div class="">
                        <p class="mb-1 fs-6 fw-bold">Kondisi CCTV Baik</p>
                        <h2 class="mb-0" id="statKondisiBaikCount">{{ number_format($yearlyHazards ?? 0) }}</h2>
                        <p class="mb-0">
                          <span class="text-success me-2 fw-medium" id="statKondisiBaikChange">0%</span>
                          <span id="statKondisiBaikText">0 CCTV</span>
                        </p>
                      </div>
                    </div>

                      <div class="vr"></div>
                    <div class="d-flex align-items-center gap-4">
                      <div class="">
                        <p class="mb-0 data-attributes">
                          <span id="donutKondisiTidakBaik"
                            data-peity='{ "fill": ["#fd7e14", "rgb(0 0 0 / 10%)"], "innerRadius": 32, "radius": 40 }'>0/100</span>
                        </p>
                      </div>
                      <div class="">
                        <p class="mb-1 fs-6 fw-bold">Kondisi CCTV Tidak Baik</p>
                        <h2 class="mb-0" id="statKondisiTidakBaikCount">{{ number_format($yearlyHazards ?? 0) }}</h2>
                        <p class="mb-0">
                          <span class="text-success me-2 fw-medium" id="statKondisiTidakBaikChange">0%</span>
                          <span id="statKondisiTidakBaikText">0 CCTV</span>
                        </p>
                      </div>
                    </div>

                    
                  </div>
               </div>
            </div>  
          </div> 
</div><!--end row-->
    </div><!--end collapse-->





</div>



@php
    $initialCriticalCoveragePercentage = $criticalCoveragePercentage ?? 95.1;
@endphp
<script>
    window.initialCriticalCoveragePercentage = {{ $initialCriticalCoveragePercentage }};
    window.chart2InitialValue = window.initialCriticalCoveragePercentage;

    // Collapse icon toggle
    document.addEventListener('DOMContentLoaded', function() {
        const collapseElement = document.getElementById('dashboardStatsCollapse');
        const collapseIcon = document.querySelector('[data-bs-target="#dashboardStatsCollapse"] .collapse-icon');
        
        if (collapseElement && collapseIcon) {
            // Set initial icon based on initial state (show = expanded)
            collapseIcon.textContent = 'expand_less';
            
            collapseElement.addEventListener('show.bs.collapse', function() {
                collapseIcon.textContent = 'expand_less';
            });
            
            collapseElement.addEventListener('hide.bs.collapse', function() {
                collapseIcon.textContent = 'expand_more';
            });
        }
    });
</script>







<!-- Full-Screen Map Container -->
<div class="map-fullscreen-container">
    <!-- Map Controls Overlay -->
    <div class="map-controls-overlay">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div class="" data-tg-tour="<strong>Hazard Location Map</strong><br><br>Peta interaktif untuk melihat lokasi hazard, CCTV, SAP, GR, Insiden, Unit, dan GPS Orang secara real-time. Gunakan filter dan layer toggle untuk menyesuaikan tampilan.">
                <h5 class="mb-0 fw-bold">Hazard Location Map</h5>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="btn-group position-static">
                    
                    <div class="btn-group position-static">
                        <button type="button" class="btn btn-filter dropdown-toggle px-3" id="mainFilterSiteBtn" data-bs-toggle="dropdown" aria-expanded="false" data-tg-tour="<strong>Filter Site</strong><br><br>Gunakan filter ini untuk memfilter data berdasarkan site/lokasi. Pilih 'Semua Site' untuk menampilkan semua data.">
                            <i class="material-icons-outlined me-2" style="font-size: 18px; vertical-align: middle;">location_on</i>
                            <span id="mainFilterSiteText">Semua Site</span>
                        </button>
                        <ul class="dropdown-menu" id="mainFilterSiteDropdown" style="max-height: 300px; overflow-y: auto;">
                            <li><a class="dropdown-item filter-option" href="javascript:;" data-value="__all__">Semua Site</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Layer Visibility Toggle Buttons -->
                <div class="btn-group position-static" role="group" aria-label="Layer visibility controls" data-tg-tour="<strong>Layer Toggle Buttons</strong><br><br>Gunakan tombol ini untuk menampilkan/menyembunyikan layer di peta:<br>• CCTV: Tampilkan lokasi kamera CCTV<br>• SAP: Tampilkan Safety Action Plan<br>• GR: Tampilkan Golden Rule violations<br>• Insiden: Tampilkan lokasi insiden<br>• Unit: Tampilkan posisi unit kendaraan<br>• GPS Orang: Tampilkan posisi GPS personel<br>• Evaluasi: Tampilkan data evaluasi">
                    <button type="button" class="btn btn-filter px-3 layer-toggle-btn active" id="toggleCctv" data-layer="cctv" title="Toggle CCTV">
                        <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">videocam</i>
                        <span>CCTV</span>
                    </button>
                    <button type="button" class="btn btn-filter px-3 layer-toggle-btn" id="toggleHazard" data-layer="hazard" title="Toggle SAP">
                        <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">assignment</i>
                        <span>SAP</span>
                    </button>
                    <button type="button" class="btn btn-filter px-3 layer-toggle-btn active" id="toggleGr" data-layer="gr" title="Toggle Golden Rule">
                        <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">rule</i>
                        <span>GR</span>
                    </button>
                    <button type="button" class="btn btn-filter px-3 layer-toggle-btn active" id="toggleInsiden" data-layer="insiden" title="Toggle Insiden">
                        <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">report_problem</i>
                        <span>Insiden</span>
                    </button>
                    <button type="button" class="btn btn-filter px-3 layer-toggle-btn" id="toggleUnit" data-layer="unit" title="Toggle Unit">
                        <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">directions_car</i>
                        <span>Unit</span>
                    </button>
                    <button type="button" class="btn btn-filter px-3 layer-toggle-btn active" id="toggleGps" data-layer="gps" title="Toggle GPS Orang">
                        <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">person_pin</i>
                        <span>GPS Orang</span>
                    </button>
                    <button type="button" class="btn btn-filter px-3 layer-toggle-btn" id="toggleEvaluasi" data-layer="evaluasi" title="Toggle Evaluasi">
                        <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">assessment</i>
                        <span>Evaluasi</span>
                    </button>
                </div>
                <button type="button" class="btn btn-filter px-3" id="btnResetMainFilter" data-tg-tour="<strong>Reset Filter</strong><br><br>Klik tombol ini untuk mereset semua filter (Perusahaan dan Site) kembali ke 'Semua'.">
                    <i class="material-icons-outlined me-2" style="font-size: 18px; vertical-align: middle;">refresh</i>
                    Reset
                </button>
            </div>
        </div>
    </div>
    
    <!-- Map Container -->
    <div class="position-relative" style="width: 100%; height: 100%;">
        <div id="hazardMap" data-tg-tour="<strong>Interactive Map</strong><br><br>Peta interaktif untuk melihat lokasi semua data. Anda dapat:<br>• Zoom in/out dengan scroll mouse<br>• Klik marker untuk melihat detail<br>• Drag untuk menggeser peta<br>• Klik area kerja/CCTV untuk evaluasi"></div>
        <div id="popup" class="ol-popup">
            <a href="#" id="popup-closer" class="ol-popup-closer"></a>
            <div id="popup-content"></div>
        </div>
        
        <!-- Icon Toolbar - Left Side -->
        <div class="map-icon-toolbar" data-tg-tour="<strong>Icon Toolbar</strong><br><br>Gunakan icon untuk melihat data berbeda:<br>• CCTV: Daftar kamera CCTV<br>• SAP: Safety Action Plan<br>• Insiden: Data insiden kecelakaan<br>• Unit: Posisi unit kendaraan<br>• GPS Orang: Lokasi personel<br>• Control Room: Data control room<br>• PJA: Pre Job Analysis<br>• Evaluasi: Summary evaluasi area">
            <button class="icon-toolbar-btn active" data-tab="cctv" title="CCTV" id="iconToolbarCctv">
                <i class="material-icons-outlined">videocam</i>
                <span class="btn-label">CCTV</span>
                <span class="icon-toolbar-badge" id="iconToolbarCctvCount">0</span>
            </button>
            <button class="icon-toolbar-btn" data-tab="sap" title="Safety Action Plan" id="iconToolbarSap">
                <i class="material-icons-outlined">assignment</i>
                <span class="btn-label">SAP</span>
                <span class="icon-toolbar-badge" id="iconToolbarSapCount">0</span>
            </button>
            <button class="icon-toolbar-btn" data-tab="insiden" title="Insiden" id="iconToolbarInsiden">
                <i class="material-icons-outlined">report_problem</i>
                <span class="btn-label">Insiden</span>
                <span class="icon-toolbar-badge" id="iconToolbarInsidenCount">0</span>
            </button>
            <button class="icon-toolbar-btn" data-tab="unit" title="Unit Kendaraan" id="iconToolbarUnit">
                <i class="material-icons-outlined">directions_car</i>
                <span class="btn-label">Unit</span>
                <span class="icon-toolbar-badge" id="iconToolbarUnitCount">0</span>
            </button>
            <button class="icon-toolbar-btn" data-tab="gps" title="GPS Orang" id="iconToolbarGps">
                <i class="material-icons-outlined">person_pin</i>
                <span class="btn-label">GPS</span>
                <span class="icon-toolbar-badge" id="iconToolbarGpsCount">0</span>
            </button>
            <div class="icon-toolbar-divider"></div>
            <button class="icon-toolbar-btn" data-tab="controlroom" title="Control Room" id="iconToolbarControlroom">
                <i class="material-icons-outlined">meeting_room</i>
                <span class="btn-label">Control</span>
                <span class="icon-toolbar-badge" id="iconToolbarControlroomCount">0</span>
            </button>
            <button class="icon-toolbar-btn" data-tab="pja" title="Pre Job Analysis" id="iconToolbarPja">
                <i class="material-icons-outlined">description</i>
                <span class="btn-label">PJA</span>
                <span class="icon-toolbar-badge" id="iconToolbarPjaCount">0</span>
            </button>
            <button class="icon-toolbar-btn" data-tab="evaluasi" title="Evaluasi" id="iconToolbarEvaluasi">
                <i class="material-icons-outlined">assessment</i>
                <span class="btn-label">Evaluasi</span>
            </button>
        </div>
        
        <!-- Sidebar Panel - Right Side -->
        <div id="mapSidebar" class="map-sidebar" data-tg-tour="<strong>Sidebar Panel</strong><br><br>Panel sidebar menampilkan daftar data berdasarkan tab yang dipilih. Gunakan icon toolbar di kiri untuk beralih antara CCTV, SAP, Insiden, Unit, GPS Orang, Control Room, PJA, dan Evaluasi. Gunakan search untuk mencari data spesifik.">
            <!-- Toggle Button -->
            <button id="sidebarToggle" class="sidebar-toggle-btn" type="button" data-tg-tour="<strong>Toggle Sidebar</strong><br><br>Klik tombol ini untuk membuka/menutup sidebar panel. Sidebar dapat di-collapse untuk memberikan lebih banyak ruang pada peta.">
                <i class="material-icons-outlined" id="sidebarToggleIcon">chevron_left</i>
            </button>
            
            <!-- Sidebar Content -->
                          <div class="sidebar-content">
                            
                            <!-- Tab Content -->
                            <div class="sidebar-body">
                                <!-- Search Bar -->
                                <div class="sidebar-search" data-tg-tour="<strong>Search & Filter</strong><br><br>Gunakan search bar untuk mencari data spesifik di tab yang aktif. Klik tombol filter untuk opsi filter tambahan.">
                                    <i class="material-icons-outlined search-icon">search</i>
                                    <input type="text" id="sidebarSearchInput" class="sidebar-search-input" placeholder="Cari...">
                                    <button type="button" class="sidebar-filter-btn" id="sidebarFilterBtn" title="Filter">
                                        <i class="material-icons-outlined">tune</i>
                                    </button>
                                </div>
                                
                                <!-- List Container -->
                                <div class="sidebar-list-container">
                                    <!-- CCTV Tab Content -->
                                    <div class="tab-content active" id="tabContentCctv">
                                        <div class="sidebar-list" id="cctvList"></div>
                                    </div>
                                    
                                    <!-- SAP Tab Content -->
                                    <div class="tab-content" id="tabContentSap">
                                        <!-- Week Filter -->
                                        <div class="sidebar-week-filter" style="padding: 12px; border-bottom: 1px solid #e5e7eb; background: #f8f9fa;">
                                            <label style="font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 8px; display: block;">Filter Week (Senin-Senin)</label>
                                            <input type="week" id="sapWeekFilter" class="form-control form-control-sm" style="font-size: 12px;">
                                            <div style="margin-top: 8px; font-size: 11px; color: #9ca3af;">
                                                <span id="sapWeekRange">Week: -</span>
                                            </div>
                                        </div>
                                        <div class="sidebar-list" id="sapList"></div>
                                    </div>
                                    
                                    <!-- Insiden Tab Content -->
                                    <div class="tab-content" id="tabContentInsiden">
                                        <div class="sidebar-list" id="insidenList"></div>
                                    </div>
                                    
                                    <!-- Unit Tab Content -->
                                    <div class="tab-content" id="tabContentUnit">
                                        <div class="sidebar-list" id="unitList"></div>
                                    </div>
                                    
                                    <!-- GPS Orang Tab Content -->
                                    <div class="tab-content" id="tabContentGps">
                                        <div class="sidebar-list" id="gpsList"></div>
                                    </div>
                                    
                                    <!-- Control Room Tab Content -->
                                    <div class="tab-content" id="tabContentControlroom">
                                        <div class="sidebar-list" id="controlroomList"></div>
                                    </div>
                                    
                                    <!-- PJA Tab Content -->
                                    <div class="tab-content" id="tabContentPja">
                                        <div class="sidebar-list" id="pjaList"></div>
                                    </div>
                                    
                                    <!-- Evaluasi Tab Content -->
                                    <div class="tab-content" id="tabContentEvaluasi">
                                        <div id="evaluasiContent" style="padding: 16px;">
                                            <div style="text-align: center; color: #6b7280; padding: 40px 20px;">
                                                <i class="material-icons-outlined" style="font-size: 48px; margin-bottom: 12px; opacity: 0.5;">assessment</i>
                                                <p style="margin: 0; font-size: 14px;">Klik area kerja atau area CCTV di peta untuk melihat summary evaluasi</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
    </div>
</div>

<!-- Modal Detail SAP -->
<div class="modal fade" id="sapDetailModal" tabindex="-1" aria-labelledby="sapDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sapDetailModalLabel">Detail SAP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="sapDetailModalBody">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk CCTV Stream Video -->
<div class="modal fade" id="cctvStreamModal" tabindex="-1" aria-labelledby="cctvStreamModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cctvStreamModalLabel">CCTV Live Stream</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="cctvStreamContainer" style="width: 100%; height: 600px; background-color: #000; position: relative;">
                    <!-- Python App iframe for stream -->
                    <iframe 
                        id="cctvStreamFrame" 
                        style="width: 100%; height: 100%; border: none; display: none; background: #000;" 
                        allowfullscreen
                        allow="autoplay; fullscreen">
                    </iframe>
                    <!-- Fallback video element (kept for backward compatibility) -->
                    <video id="cctvStreamVideo" style="width: 100%; height: 100%; display: none; background: #000;" controls autoplay muted playsinline></video>
                    <div id="cctvStreamLoading" class="position-absolute top-50 start-50 translate-middle text-center text-white" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 mb-0">Memuat stream video dari Python...</p>
                        <small class="text-white-50 d-block">Pastikan aplikasi Python berjalan di localhost:5000</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnRefreshStream" onclick="refreshCurrentStream()">
                    <i class="material-icons-outlined me-1" style="font-size: 16px;">refresh</i>
                    Refresh Stream
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>




@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/ol@8.2.0/dist/ol.js"></script>
<script src="https://cdn.jsdelivr.net/npm/proj4@2.9.0/dist/proj4.js"></script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>
<script src="{{ URL::asset('build/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ URL::asset('build/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<!-- Load BMO2 PAMA GeoJSON data -->
<script src="{{ asset('js/area-kerja-bmo2-pama.js') }}"></script>
<script src="{{ asset('js/area-cctv-bmo2-pama.js') }}"></script>
<script src="{{ asset('js/difference_bmo2-pama.js') }}"></script>
<script src="{{ asset('js/symmetrical_difference_bmo2-pama.js') }}"></script>
<script src="{{ asset('js/intersection_bmo2-pama.js') }}"></script>

<!-- Load Area CCTV GeoJSON data -->
<script src="{{ asset('js/area_cctv_bmo1_fad.js') }}"></script>
<script src="{{ asset('js/area_cctv_bmo1_kdc.js') }}"></script>
<script src="{{ asset('js/area_cctv_bmo2_buma.js') }}"></script>
<script src="{{ asset('js/area_cctv_bmo2_pama.js') }}"></script>
<script src="{{ asset('js/area_cctv_bmo3_bar.js') }}"></script>
<script src="{{ asset('js/area_cctv_gmo_kdc.js') }}"></script>
<script src="{{ asset('js/area_cctv_gmo_pama.js') }}"></script>
<script src="{{ asset('js/area_cctv_lmo_buma.js') }}"></script>
<script src="{{ asset('js/area_cctv_lmo_fad.js') }}"></script>
<script src="{{ asset('js/area_cctv_smo_mtn.js') }}"></script>

<!-- Load Area Kerja GeoJSON data -->
<script src="{{ asset('js/area_kerja_bmo1_fad.js') }}"></script>
<script src="{{ asset('js/area_kerja_bmo1_kdc.js') }}"></script>
<script src="{{ asset('js/area_kerja_bmo2_buma.js') }}"></script>
<script src="{{ asset('js/area_kerja_bmo3_bar.js') }}"></script>
<script src="{{ asset('js/area_kerja_gmo_kdc.js') }}"></script>
<script src="{{ asset('js/area_kerja_gmo_pama.js') }}"></script>
<script src="{{ asset('js/area_kerja_lmo_buma.js') }}"></script>
<script src="{{ asset('js/area_kerja_lmo_fad.js') }}"></script>
<script src="{{ asset('js/area_kerja_smo_mtn.js') }}"></script>

<script>
    // Calculate and update area kerja and CCTV coverage
    function calculateAreaCoverage() {
        try {
            // Check if data is available
            if (typeof window.areaKerjaGeoJsonDataPama === 'undefined' || 
                typeof window.areaCctvGeoJsonDataBmo2Pama === 'undefined') {
                console.log('Waiting for GeoJSON data to load...');
                setTimeout(calculateAreaCoverage, 200);
                return;
            }
            
            // Get area kerja data
            const areaKerjaData = window.areaKerjaGeoJsonDataPama;
            // Get CCTV data
            const areaCctvData = window.areaCctvGeoJsonDataBmo2Pama;
            
            // Calculate total luasan area kerja
            let totalLuasanAreaKerja = 0;
            if (areaKerjaData && areaKerjaData.features && Array.isArray(areaKerjaData.features)) {
                areaKerjaData.features.forEach(feature => {
                    if (feature.properties && feature.properties.luasan) {
                        totalLuasanAreaKerja += parseFloat(feature.properties.luasan) || 0;
                    }
                });
            }
            
            // Calculate total luasan CCTV and count
            let totalLuasanCctv = 0;
            let totalCctvCount = 0;
            if (areaCctvData && areaCctvData.features && Array.isArray(areaCctvData.features)) {
                totalCctvCount = areaCctvData.features.length;
                areaCctvData.features.forEach(feature => {
                    if (feature.properties && feature.properties.luasan) {
                        totalLuasanCctv += parseFloat(feature.properties.luasan) || 0;
                    }
                });
            }
            
            // Calculate coverage percentage
            let coveragePercentage = 0;
            if (totalLuasanAreaKerja > 0) {
                coveragePercentage = (totalLuasanCctv / totalLuasanAreaKerja) * 100;
            }
            
            // Format luasan function
            function formatLuasan(luasan) {
                if (luasan >= 1000000) {
                    return (luasan / 1000000).toFixed(2) + ' km²';
                } else if (luasan >= 10000) {
                    return (luasan / 10000).toFixed(2) + ' ha';
                } else {
                    return luasan.toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' m²';
                }
            }
            
            // Update UI elements
            const luasanAreaKerjaEl = document.getElementById('luasanAreaKerja');
            const luasanCctvEl = document.getElementById('luasanCctv');
            const totalCctvCountEl = document.getElementById('totalCctvCount');
            const coveragePercentageEl = document.getElementById('coveragePercentage');
            const coverageBadgeEl = document.getElementById('coverageBadge');
            
            // Update Luasan Area Kerja
            if (luasanAreaKerjaEl) {
                luasanAreaKerjaEl.textContent = formatLuasan(totalLuasanAreaKerja);
            }
            
            // Update Luasan CCTV
            if (luasanCctvEl) {
                luasanCctvEl.textContent = formatLuasan(totalLuasanCctv);
            }
            
            // Update Total CCTV Count
            if (totalCctvCountEl) {
                totalCctvCountEl.textContent = totalCctvCount.toLocaleString('id-ID');
            }
            
            // Update Coverage Percentage
            if (coveragePercentageEl) {
                coveragePercentageEl.textContent = coveragePercentage.toFixed(2) + '%';
            }
            
            // Update Coverage Badge
            if (coverageBadgeEl) {
                coverageBadgeEl.textContent = coveragePercentage.toFixed(2) + '% Coverage';
            }
            
            console.log('Area Coverage Calculated:', {
                totalLuasanAreaKerja: totalLuasanAreaKerja.toLocaleString('id-ID'),
                totalLuasanCctv: totalLuasanCctv.toLocaleString('id-ID'),
                totalCctvCount: totalCctvCount,
                coveragePercentage: coveragePercentage.toFixed(2) + '%'
            });
        } catch (error) {
            console.error('Error calculating area coverage:', error);
        }
    }
    
    // Run calculation when DOM is ready and data is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a bit for GeoJSON files to load
            setTimeout(calculateAreaCoverage, 300);
        });
    } else {
        // DOM already loaded, wait for GeoJSON files
        setTimeout(calculateAreaCoverage, 300);
    }
</script>
<script>
    // SAP (Safety Action Plan) data dari ClickHouse
    // Mengganti hazard dengan SAP dari tabel nitip.union_sap_all_with_karyawan_full
    // Data ini akan di-overwrite oleh loadSapDataByWeek() berdasarkan week filter
    // Filter hanya SAP hari ini untuk performa (mengurangi lag)
    let allSapData = @json($sapData ?? []);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayStr = today.toISOString().split('T')[0]; // Format: YYYY-MM-DD
    
    // OPTIMIZED: Filter hanya SAP hari ini dengan early exit untuk performa
    // Sidebar: hanya data hari ini, Map: maksimal 1000 terbaru
    // Deklarasi di scope global agar bisa diakses oleh fungsi lain
    var sapDataForSidebar = []; // Data hari ini untuk sidebar
    var sapData = []; // Data terbatas untuk map (1000 terbaru)
    var sapDataAllWeek = []; // Semua data per week (untuk count di tab)
    
    if (allSapData && allSapData.length > 0) {
        // Filter data hari ini (semua data, tidak dibatasi)
        const todaySapData = [];
        for (let i = 0; i < allSapData.length; i++) {
            const sap = allSapData[i];
            if (!sap.tanggal_pelaporan && !sap.detected_at) continue;
            
            try {
                const sapDate = new Date(sap.tanggal_pelaporan || sap.detected_at);
                sapDate.setHours(0, 0, 0, 0);
                const sapDateStr = sapDate.toISOString().split('T')[0];
                if (sapDateStr === todayStr) {
                    todaySapData.push(sap);
                }
            } catch (e) {
                // Skip invalid dates
                continue;
            }
        }
        
        // Urutkan berdasarkan tanggal terbaru untuk sidebar
        sapDataForSidebar = todaySapData.sort((a, b) => {
            const dateA = new Date(a.tanggal_pelaporan || a.detected_at || 0);
            const dateB = new Date(b.tanggal_pelaporan || b.detected_at || 0);
            return dateB - dateA; // Terbaru di atas
        });
        
        // Untuk data awal, sapDataAllWeek juga diisi dengan data hari ini (akan di-overwrite saat week filter dipilih)
        sapDataAllWeek = [...sapDataForSidebar];
        
        // Map: Hanya ambil 1000 terbaru
        sapData = sapDataForSidebar.slice(0, 1000);
    }
    
    console.log(`Filtered SAP data: Sidebar (today) ${sapDataForSidebar.length} items | Map ${sapData.length} items (limited to 1000) for today (${todayStr}) out of ${allSapData.length} total`);
    
    const hazardDetections = sapData; // Alias untuk kompatibilitas dengan kode yang sudah ada
    
    // Data CCTV diambil langsung dari database (tabel cctv_data_bmo2), bukan dari WMS atau GeoJSON
    // cctvLocations: SEMUA data CCTV (2035) untuk ditampilkan di sidebar
    // cctvLocationsForMap: Hanya CCTV yang punya koordinat (1789) untuk ditampilkan di map
    const cctvLocations = @json($cctvLocations);
    const cctvLocationsForMap = @json($cctvLocationsForMap ?? $cctvLocations);
    
    const grDetections = @json($grDetections ?? []);
    const insidenDataset = @json($insidenGroups);
    const insidenDatasetMap = new Map(insidenDataset.map(item => [item.no_kecelakaan, item]));
    let unitVehicles = @json($unitVehicles ?? []);
    const luasanComparisons = [];
    let currentHlsInstance = null;
    const defaultCctvRtspUrl = 'rtsp://gkr:Berau2025!@10.1.162.180:554/hocomdev';
    
    // Python App Configuration
    const pythonAppUrl = 'http://localhost:5000';
    
    // Current stream data for refresh functionality
    let currentStreamData = {
        cctvName: null,
        rtspUrl: null
    };

    // WMS Server Configuration
    const wmsServers = {
        smo: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_SMO_BLOCK_B1_2510/MapServer/WMSServer',
            name: 'SMO Block B1',
            bbox: [117.402228, 2.150819, 117.505579, 2.221687],
            center: [117.4539035, 2.186253]
        },
        smoA: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_SMO_BLOCK_A/MapServer/WMSServer',
            name: 'SMO Block A',
            bbox: [117.378740, 2.154163, 117.409737, 2.199252],
            center: [117.3942385, 2.1767075]
        },
        smoBEastWest: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_SMO_BLOCK_B_EAST_WEST/MapServer/WMSServer',
            name: 'SMO Block B East-West',
            bbox: [117.333284, 2.166645, 117.420828, 2.333354],
            center: [117.377056, 2.2499995]
        },
        bmo: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_BMO_BLOCK_1_4/MapServer/WMSServer',
            name: 'BMO Block 1-4',
            bbox: [117.437891, 2.026662, 117.483348, 2.122948],
            center: [117.4606195, 2.074805]
        },
        bmo56: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_BMO_BLOCK_5_6/MapServer/WMSServer',
            name: 'BMO Block 5-6',
            bbox: [117.405839, 1.971650, 117.475021, 2.095264],
            center: [117.44043, 2.033457]
        },
        bmo7: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_BMO_BLOCK_7/MapServer/WMSServer',
            name: 'BMO Block 7',
            bbox: [117.312358, 1.941393, 117.426208, 2.036692],
            center: [117.369283, 1.9890425]
        },
        bmo8: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_BMO_BLOCK_8/MapServer/WMSServer',
            name: 'BMO Block 8',
            bbox: [117.143050, 1.873312, 117.353350, 2.000030],
            center: [117.2482, 1.936671]
        },
        bmo9: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_BMO_BLOCK_9/MapServer/WMSServer',
            name: 'BMO Block 9',
            bbox: [117.129991, 1.936764, 117.183360, 2.043340],
            center: [117.1566755, 1.990052]
        },
        bmo10: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_BMO_BLOCK_10/MapServer/WMSServer',
            name: 'BMO Block 10',
            bbox: [117.166646, 2.033321, 117.241680, 2.132808],
            center: [117.204163, 2.0830645]
        },
        bmoParapatan: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_BMO_BLOCK_PARAPATAN/MapServer/WMSServer',
            name: 'BMO Block Parapatan',
            bbox: [117.431663, 2.090234, 117.483621, 2.146128],
            center: [117.457642, 2.118181]
        },
        gurimbang: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_GURIMBANG/MapServer/WMSServer',
            name: 'Gurimbang',
            bbox: [117.483325, 2.091636, 117.625039, 2.212991],
            center: [117.554182, 2.1523135]
        },
        khdtk: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_KHDTK/MapServer/WMSServer',
            name: 'KHDTK',
            bbox: [117.175827, 1.900871, 117.241266, 1.948915],
            center: [117.2085465, 1.924893]
        },
        punan: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_PUNAN/MapServer/WMSServer',
            name: 'Punan',
            bbox: [117.204989, 2.166649, 117.333347, 2.248363],
            center: [117.269168, 2.207506]
        },
        lati: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_LATI_2510/MapServer/WMSServer',
            name: 'Lati',
            bbox: [117.509916, 2.189945, 117.638408, 2.418367],
            center: [117.574162, 2.304156]
        }
    };
    
    // Current WMS server
    let currentWmsServer = 'smo';
    let wmsUrl = wmsServers[currentWmsServer].url;
    let currentLayer = '';
    let wmsLayer = null;
    let hazardLayer = null;
    let cctvLayer = null;
    let grLayer = null;
    let insidenLayer = null;
    let unitVehicleLayer = null;
    let userGpsLayer = null;
    let popupOverlay = null;
    
    // Site filter - harus didefinisikan sebelum digunakan di style function
    let currentSiteFilter = '';
    
    // Sidebar Panel Management - harus didefinisikan sebelum digunakan
    let currentSidebarTab = 'cctv';
    let sidebarCollapsed = false;
    let filteredSidebarData = {
        cctv: [],
        sap: [],
        insiden: [],
        unit: [],
        gps: [],
        controlroom: [],
        pja: []
    };
    
    // Store original PJA data for filtering
    let originalPjaData = [];
    
    // Store original Control Room data for filtering
    let originalControlRoomData = [];
    
    // Layer visibility state
    // Default: SAP dan Unit hidden untuk performa
    let layerVisibility = {
        cctv: true,
        hazard: false,  // SAP default hidden
        gr: true,
        insiden: true,
        unit: false,    // Unit default hidden
        gps: true
    };
    
    // BMO2 PAMA GeoJSON layers
    let areaKerjaBmo2PamaLayer = null;
    let areaCctvBmo2PamaLayer = null;
    let differenceBmo2PamaLayer = null;
    let symmetricalDifferenceBmo2PamaLayer = null;
    let intersectionBmo2PamaLayer = null;
    
    // Highlight layer for selected area kerja / luasan
    let highlightedAreaKerjaLayer = null;
    let highlightedLuasanLayer = null;

    // Create Google Satellite tile source (fallback)
    const googleSatelliteSource = new ol.source.XYZ({
        url: 'http://mt0.google.com/vt/lyrs=s&hl=en&x={x}&y={y}&z={z}',
        attributions: '© Google',
        maxZoom: 20
    });

    // Function to create layer from GeoJSON data (for CRS84/EPSG:4326 data)
    function createLayerFromGeoJson(geoJsonData, layerName, styleFunction, zIndex = 300) {
        if (!geoJsonData) {
            console.warn(`${layerName}: GeoJSON data is null or undefined`);
            return new ol.layer.Vector({
                source: new ol.source.Vector(),
                name: layerName,
                zIndex: zIndex,
                visible: true
            });
        }

        if (!geoJsonData.features || geoJsonData.features.length === 0) {
            console.warn(`${layerName}: GeoJSON data has no features (features: ${geoJsonData.features ? geoJsonData.features.length : 'null'})`);
            return new ol.layer.Vector({
                source: new ol.source.Vector(),
                name: layerName,
                zIndex: zIndex,
                visible: true
            });
        }

        try {
            console.log(`${layerName}: Parsing ${geoJsonData.features.length} features...`);
            const features = new ol.format.GeoJSON().readFeatures(geoJsonData, {
                dataProjection: 'EPSG:4326',
                featureProjection: 'EPSG:3857'
            });

            console.log(`${layerName}: Successfully parsed ${features.length} features`);

            return new ol.layer.Vector({
                source: new ol.source.Vector({
                    features: features
                }),
                style: styleFunction,
                name: layerName,
                zIndex: zIndex,
                visible: true
            });
        } catch (error) {
            console.error(`${layerName}: Error parsing GeoJSON:`, error);
            console.error('GeoJSON data sample:', JSON.stringify(geoJsonData).substring(0, 200));
            return new ol.layer.Vector({
                source: new ol.source.Vector(),
                name: layerName,
                zIndex: zIndex,
                visible: true
            });
        }
    }

    // Function to create layer from GeoJSON data with EPSG:32650 (UTM Zone 50N)
    function createLayerFromGeoJson32650(geoJsonData, layerName, styleFunction, zIndex = 300) {
        if (!geoJsonData) {
            console.warn(`${layerName}: GeoJSON data is null or undefined`);
            return new ol.layer.Vector({
                source: new ol.source.Vector(),
                name: layerName,
                zIndex: zIndex,
                visible: true
            });
        }

        if (!geoJsonData.features || geoJsonData.features.length === 0) {
            console.warn(`${layerName}: GeoJSON data has no features (features: ${geoJsonData.features ? geoJsonData.features.length : 'null'})`);
            return new ol.layer.Vector({
                source: new ol.source.Vector(),
                name: layerName,
                zIndex: zIndex,
                visible: true
            });
        }

        try {
            console.log(`${layerName}: Parsing ${geoJsonData.features.length} features from EPSG:32650...`);
            
            // Register EPSG:32650 projection using proj4js
            if (typeof proj4 === 'undefined') {
                console.error(`${layerName}: proj4js library is required for EPSG:32650 transformation`);
                return new ol.layer.Vector({
                    source: new ol.source.Vector(),
                    name: layerName,
                    zIndex: zIndex,
                    visible: true
                });
            }
            
            // Define EPSG:32650 projection
            proj4.defs('EPSG:32650', '+proj=utm +zone=50 +datum=WGS84 +units=m +no_defs');
            
            // Register with OpenLayers
            if (typeof ol.proj.proj4 !== 'undefined') {
                ol.proj.proj4.register(proj4);
            }
            
            // Transform geometry coordinates recursively
            const transformGeometry = (geometry) => {
                if (!geometry || !geometry.coordinates) {
                    console.warn(`${layerName}: Geometry is null or has no coordinates`);
                    return geometry;
                }
                
                const transformCoordinate = (coord) => {
                    if (!Array.isArray(coord)) {
                        return coord;
                    }
                    
                    // Check if this is a coordinate pair [x, y] or [x, y, z]
                    if (coord.length >= 2 && 
                        typeof coord[0] === 'number' && 
                        typeof coord[1] === 'number' &&
                        (coord.length === 2 || (coord.length === 3 && typeof coord[2] === 'number'))) {
                        // This is a coordinate pair [x, y] or [x, y, z]
                        try {
                            // Transform from EPSG:32650 (UTM) to EPSG:4326 (WGS84)
                            const wgs84 = proj4('EPSG:32650', 'EPSG:4326', [coord[0], coord[1]]);
                            // Transform from EPSG:4326 to EPSG:3857 (Web Mercator)
                            const webMercator = ol.proj.transform(wgs84, 'EPSG:4326', 'EPSG:3857');
                            // Preserve z coordinate if present
                            if (coord.length === 3) {
                                return [webMercator[0], webMercator[1], coord[2]];
                            }
                            return webMercator;
                        } catch (e) {
                            console.warn(`${layerName}: Transform error for coord [${coord[0]}, ${coord[1]}]:`, e);
                            return coord;
                        }
                    } else {
                        // This is a nested array (ring or polygon), recurse
                        return coord.map(transformCoordinate);
                    }
                };
                
                try {
                    const transformedGeometry = JSON.parse(JSON.stringify(geometry));
                    transformedGeometry.coordinates = transformCoordinate(geometry.coordinates);
                    return transformedGeometry;
                } catch (e) {
                    console.error(`${layerName}: Error cloning geometry:`, e);
                    return geometry;
                }
            };
            
            // Transform all features
            const transformedFeatures = geoJsonData.features.map(feature => {
                const transformedFeature = {
                    type: 'Feature',
                    properties: feature.properties || {},
                    geometry: transformGeometry(feature.geometry)
                };
                
                // Read feature with transformed geometry (already in EPSG:3857)
                return new ol.format.GeoJSON().readFeature(transformedFeature, {
                    dataProjection: 'EPSG:3857',
                    featureProjection: 'EPSG:3857'
                });
            });

            console.log(`${layerName}: Successfully parsed ${transformedFeatures.length} features`);

            return new ol.layer.Vector({
                source: new ol.source.Vector({
                    features: transformedFeatures
                }),
                style: styleFunction,
                name: layerName,
                zIndex: zIndex,
                visible: true
            });
        } catch (error) {
            console.error(`${layerName}: Error parsing GeoJSON:`, error);
            console.error('Error details:', error.message);
            console.error('Error stack:', error.stack);
            return new ol.layer.Vector({
                source: new ol.source.Vector(),
                name: layerName,
                zIndex: zIndex,
                visible: true
            });
        }
    }

    // Style functions for different layers
    function getAreaKerjaStyle(feature) {
        const props = feature.getProperties();
        const areaKerja = props.area_kerja || '';
        
        let fillColor = 'rgba(16, 185, 129, 0.4)'; // Green default - increased opacity
        let strokeColor = '#10b981';
        let strokeWidth = 2;
        
        if (areaKerja === 'Pit') {
            fillColor = 'rgba(239, 68, 68, 0.4)'; // Red - increased opacity
            strokeColor = '#ef4444';
            strokeWidth = 2;
        } else if (areaKerja === 'Hauling') {
            fillColor = 'rgba(245, 158, 11, 0.4)'; // Orange - increased opacity
            strokeColor = '#f59e0b';
            strokeWidth = 2;
        } else if (areaKerja === 'Infra Tambang') {
            fillColor = 'rgba(59, 130, 246, 0.4)'; // Blue - increased opacity
            strokeColor = '#3b82f6';
            strokeWidth = 2;
        }
        
        return new ol.style.Style({
            fill: new ol.style.Fill({
                color: fillColor
            }),
            stroke: new ol.style.Stroke({
                color: strokeColor,
                width: strokeWidth
            })
        });
    }

    function getAreaCctvStyle(feature) {
        return new ol.style.Style({
            fill: new ol.style.Fill({
                color: 'rgba(139, 92, 246, 0.3)' // Purple with transparency
            }),
            stroke: new ol.style.Stroke({
                color: '#8b5cf6', // Purple
                width: 2
            })
        });
    }

    function getDifferenceStyle(feature) {
        return new ol.style.Style({
            fill: new ol.style.Fill({
                color: 'rgba(239, 68, 68, 0.4)' // Red with transparency
            }),
            stroke: new ol.style.Stroke({
                color: '#ef4444', // Red
                width: 2
            })
        });
    }

    function getSymmetricalDifferenceStyle(feature) {
        return new ol.style.Style({
            fill: new ol.style.Fill({
                color: 'rgba(245, 158, 11, 0.4)' // Orange with transparency
            }),
            stroke: new ol.style.Stroke({
                color: '#f59e0b', // Orange
                width: 2
            })
        });
    }

    function getIntersectionStyle(feature) {
        return new ol.style.Style({
            fill: new ol.style.Fill({
                color: 'rgba(34, 197, 94, 0.4)' // Green with transparency
            }),
            stroke: new ol.style.Stroke({
                color: '#22c55e', // Green
                width: 2
            })
        });
    }

    // Function to create WMS layer
    function createWMSLayer(layerName = '', serverKey = currentWmsServer) {
        const server = wmsServers[serverKey];
        const params = {
            'LAYERS': layerName || '0',
            'VERSION': '1.1.1',
            'FORMAT': 'image/png',
            'TRANSPARENT': true,
            'TILED': true
        };
        
        return new ol.layer.Tile({
            source: new ol.source.TileWMS({
                url: server.url,
                params: params,
                serverType: 'mapserver',
                crossOrigin: 'anonymous',
                tileGrid: new ol.tilegrid.TileGrid({
                    extent: ol.proj.transformExtent(
                        server.bbox,
                        'EPSG:4326',
                        'EPSG:3857'
                    ),
                    resolutions: [
                        156543.03392804097,
                        78271.51696402048,
                        39135.75848201024,
                        19567.87924100512,
                        9783.93962050256,
                        4891.96981025128,
                        2445.98490512564,
                        1222.99245256282,
                        611.49622628141,
                        305.748113140705,
                        152.8740565703525,
                        76.43702828517625,
                        38.21851414258813,
                        19.109257071294063,
                        9.554628535647032,
                        4.777314267823516,
                        2.388657133911758,
                        1.194328566955879,
                        0.5971642834779395
                    ],
                    tileSize: [256, 256]
                })
            }),
            zIndex: 1,
            opacity: 0.85
        });
    }

    // Create map dengan Google Satellite sebagai base layer
    const map = new ol.Map({
        target: 'hazardMap',
        layers: [
            // Base layer - Google Satellite (fallback)
            new ol.layer.Tile({
                source: googleSatelliteSource,
                opacity: 1.0
            })
        ],
        view: new ol.View({
            center: ol.proj.fromLonLat(wmsServers[currentWmsServer].center),
            zoom: 15
        }),
        controls: [
            new ol.control.Zoom(),
            new ol.control.ScaleLine(),
            new ol.control.MousePosition({
                coordinateFormat: function(coordinate) {
                    if (coordinate) {
                        return coordinate[0].toFixed(4) + ', ' + coordinate[1].toFixed(4);
                    }
                    return '';
                },
                projection: 'EPSG:4326'
            })
        ]
    });

    // Create vector layer for SAP (Safety Action Plan) - mengganti hazard
    // Default hidden untuk performa, akan muncul saat toggle diklik
    hazardLayer = new ol.layer.Vector({
        source: new ol.source.Vector(),
        visible: false,  // Default hidden
        style: function(feature) {
            // Check site filter
            if (currentSiteFilter) {
                const sapData = feature.get('data');
                if (sapData) {
                    const sapSite = sapData.site || null;
                    if (sapSite !== currentSiteFilter) {
                        return null; // Hide feature if doesn't match filter
                    }
                } else {
                    return null; // Hide if no data
                }
            }
            
            const jenisLaporan = feature.get('jenis_laporan');
            
            // Warna berdasarkan jenis laporan SAP
            let color = '#3b82f6'; // default blue untuk SAP
            if (jenisLaporan === 'OBSERVASI') color = '#3b82f6';
            else if (jenisLaporan === 'INSPEKSI') color = '#10b981';
            else if (jenisLaporan === 'AUDIT') color = '#f59e0b';
            else color = '#6b7280';
            
            return new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 10,
                    fill: new ol.style.Fill({ color: color }),
                    stroke: new ol.style.Stroke({
                        color: '#ffffff',
                        width: 2
                    })
                })
            });
        },
        zIndex: 1000  // Z-index tinggi agar selalu di atas WMS layer
    });
    map.addLayer(hazardLayer);

    // Create vector layer for insiden
    insidenLayer = new ol.layer.Vector({
        source: new ol.source.Vector(),
        style: function(feature) {
            // Check site filter
            if (currentSiteFilter) {
                const insidenData = feature.get('data');
                if (insidenData) {
                    const insidenSite = insidenData.site || null;
                    if (insidenSite !== currentSiteFilter) {
                        return null; // Hide feature if doesn't match filter
                    }
                } else {
                    return null; // Hide if no data
                }
            }
            
            return new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 9,
                    fill: new ol.style.Fill({ color: '#f97316' }),
                    stroke: new ol.style.Stroke({
                        color: '#ffffff',
                        width: 2
                    })
                })
            });
        },
        zIndex: 1001
    });
    map.addLayer(insidenLayer);

    // Add SAP markers (mengganti hazard markers) - OPTIMIZED dengan batch rendering
    // Limit jumlah marker untuk performa (maksimal 1000 marker)
    const MAX_SAP_MARKERS = 1000;
    const BATCH_SIZE = 100; // Render dalam batch untuk performa
    
    function addSapMarkersBatch(sapDataArray) {
        if (!sapDataArray || sapDataArray.length === 0) return;
        
        let markerCount = 0;
        const source = hazardLayer.getSource();
        const features = [];
        
        // Filter dan prepare features
        sapDataArray.forEach(function(sap) {
            if (markerCount >= MAX_SAP_MARKERS) return;
            if (!sap.location || !sap.location.lat || !sap.location.lng) return;
            
            const feature = new ol.Feature({
                geometry: new ol.geom.Point(
                    ol.proj.fromLonLat([sap.location.lng, sap.location.lat])
                ),
                id: sap.id,
                task_number: sap.task_number,
                jenis_laporan: sap.jenis_laporan,
                aktivitas_pekerjaan: sap.aktivitas_pekerjaan,
                lokasi: sap.lokasi,
                description: sap.description,
                data: sap
            });
            features.push(feature);
            markerCount++;
        });
        
        // Batch add features untuk performa
        if (features.length > 0) {
            // Add dalam batch menggunakan requestAnimationFrame
            let index = 0;
            function addBatch() {
                const batch = features.slice(index, index + BATCH_SIZE);
                if (batch.length > 0) {
                    source.addFeatures(batch);
                    index += BATCH_SIZE;
                    if (index < features.length) {
                        requestAnimationFrame(addBatch);
                    } else {
                        console.log(`Added ${features.length} SAP markers to map (filtered for today) in batches`);
                    }
                }
            }
            requestAnimationFrame(addBatch);
        }
    }
    
    // Defer marker rendering sampai map ready
    setTimeout(() => {
        addSapMarkersBatch(sapData);
    }, 500);

    // Function to create CCTV icon from HTML/CSS - Enhanced Design
    function createCCTVIcon(cctv) {
        const canvas = document.createElement('canvas');
        canvas.width = 64;
        canvas.height = 64;
        const ctx = canvas.getContext('2d');
        
        // Clear canvas
        ctx.clearRect(0, 0, 64, 64);
        
        // Draw pin shape (rotated square with better design)
        ctx.save();
        ctx.translate(32, 32);
        ctx.rotate(-45 * Math.PI / 180);
        
        // Outer glow/shadow effect
        ctx.shadowColor = 'rgba(59, 130, 246, 0.4)';
        ctx.shadowBlur = 8;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 0;
        
        // Main pin body with gradient
        const pinGradient = ctx.createLinearGradient(-20, -20, 20, 20);
        pinGradient.addColorStop(0, '#60a5fa');  // Lighter blue
        pinGradient.addColorStop(0.5, '#3b82f6'); // Main blue
        pinGradient.addColorStop(1, '#1e40af');   // Darker blue
        ctx.fillStyle = pinGradient;
        ctx.beginPath();
        ctx.roundRect(-20, -20, 40, 40, 6);
        ctx.fill();
        
        // Inner highlight for 3D effect
        ctx.shadowBlur = 0;
        const highlightGradient = ctx.createLinearGradient(-18, -18, -8, -8);
        highlightGradient.addColorStop(0, 'rgba(255, 255, 255, 0.4)');
        highlightGradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
        ctx.fillStyle = highlightGradient;
        ctx.beginPath();
        ctx.roundRect(-18, -18, 12, 12, 3);
        ctx.fill();
        
        // White border with shadow
        ctx.shadowColor = 'rgba(0, 0, 0, 0.2)';
        ctx.shadowBlur = 3;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 2;
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 3.5;
        ctx.beginPath();
        ctx.roundRect(-20, -20, 40, 40, 6);
        ctx.stroke();
        
        ctx.restore();
        
        // Draw professional camera icon in center
        ctx.save();
        ctx.translate(32, 32);
        ctx.rotate(45 * Math.PI / 180);
        
        // Camera body with gradient
        const cameraBodyGradient = ctx.createLinearGradient(-10, -8, -10, 8);
        cameraBodyGradient.addColorStop(0, '#f8fafc');
        cameraBodyGradient.addColorStop(0.5, '#ffffff');
        cameraBodyGradient.addColorStop(1, '#e2e8f0');
        ctx.fillStyle = cameraBodyGradient;
        ctx.beginPath();
        ctx.roundRect(-10, -8, 20, 16, 3);
        ctx.fill();
        
        // Camera body border
        ctx.strokeStyle = '#cbd5e1';
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.roundRect(-10, -8, 20, 16, 3);
        ctx.stroke();
        
        // Camera lens outer ring
        const lensGradient = ctx.createRadialGradient(0, 0, 0, 0, 0, 6);
        lensGradient.addColorStop(0, '#1e3a8a');
        lensGradient.addColorStop(0.7, '#1e40af');
        lensGradient.addColorStop(1, '#1e293b');
        ctx.fillStyle = lensGradient;
        ctx.beginPath();
        ctx.arc(0, 0, 6, 0, 2 * Math.PI);
        ctx.fill();
        
        // Camera lens inner (glass reflection)
        ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
        ctx.beginPath();
        ctx.arc(-1.5, -1.5, 3, 0, 2 * Math.PI);
        ctx.fill();
        
        // Camera lens center (aperture)
        ctx.fillStyle = '#0f172a';
        ctx.beginPath();
        ctx.arc(0, 0, 2.5, 0, 2 * Math.PI);
        ctx.fill();
        
        // Camera flash/light
        const flashGradient = ctx.createLinearGradient(8, -5, 12, -1);
        flashGradient.addColorStop(0, '#fef3c7');
        flashGradient.addColorStop(1, '#fbbf24');
        ctx.fillStyle = flashGradient;
        ctx.beginPath();
        ctx.roundRect(8, -5, 6, 6, 1.5);
        ctx.fill();
        
        // Flash highlight
        ctx.fillStyle = 'rgba(255, 255, 255, 0.6)';
        ctx.beginPath();
        ctx.roundRect(9, -4, 3, 3, 1);
        ctx.fill();
        
        // Camera viewfinder (top)
        ctx.fillStyle = '#1e293b';
        ctx.beginPath();
        ctx.roundRect(-4, -12, 8, 3, 1);
        ctx.fill();
        
        // Viewfinder highlight
        ctx.fillStyle = 'rgba(255, 255, 255, 0.2)';
        ctx.beginPath();
        ctx.roundRect(-3, -11.5, 6, 1, 0.5);
        ctx.fill();
        
        ctx.restore();
        
        // Live indicator (enhanced) if status is Live View
        if (cctv.status === 'Live View' || cctv.status === 'live') {
            ctx.save();
            ctx.translate(32, 32);
            
            // Outer pulse ring
            ctx.strokeStyle = '#10b981';
            ctx.lineWidth = 2;
            ctx.globalAlpha = 0.3;
            ctx.beginPath();
            ctx.arc(18, -18, 7, 0, 2 * Math.PI);
            ctx.stroke();
            
            // Middle pulse ring
            ctx.globalAlpha = 0.5;
            ctx.beginPath();
            ctx.arc(18, -18, 5, 0, 2 * Math.PI);
            ctx.stroke();
            
            // Main live indicator dot
            ctx.globalAlpha = 1;
            const liveGradient = ctx.createRadialGradient(18, -18, 0, 18, -18, 5);
            liveGradient.addColorStop(0, '#34d399');
            liveGradient.addColorStop(0.7, '#10b981');
            liveGradient.addColorStop(1, '#059669');
            ctx.fillStyle = liveGradient;
            ctx.beginPath();
            ctx.arc(18, -18, 5, 0, 2 * Math.PI);
            ctx.fill();
            
            // White border for live indicator
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(18, -18, 5, 0, 2 * Math.PI);
            ctx.stroke();
            
            // Inner highlight
            ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
            ctx.beginPath();
            ctx.arc(17, -19, 2, 0, 2 * Math.PI);
            ctx.fill();
            
            ctx.restore();
        }
        
        return canvas.toDataURL();
    }
    
    // Helper function to draw rounded rectangle
    if (!CanvasRenderingContext2D.prototype.roundRect) {
        CanvasRenderingContext2D.prototype.roundRect = function(x, y, width, height, radius) {
            this.beginPath();
            this.moveTo(x + radius, y);
            this.lineTo(x + width - radius, y);
            this.quadraticCurveTo(x + width, y, x + width, y + radius);
            this.lineTo(x + width, y + height - radius);
            this.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
            this.lineTo(x + radius, y + height);
            this.quadraticCurveTo(x, y + height, x, y + height - radius);
            this.lineTo(x, y + radius);
            this.quadraticCurveTo(x, y, x + radius, y);
            this.closePath();
        };
    }

    // Add CCTV markers
    cctvLayer = new ol.layer.Vector({
        source: new ol.source.Vector(),
        style: function(feature) {
            // Check site filter
            if (currentSiteFilter) {
                const cctvData = feature.get('cctvData');
                if (cctvData) {
                    const cctvSite = cctvData.site || null;
                    if (cctvSite !== currentSiteFilter) {
                        return null; // Hide feature if doesn't match filter
                    }
                } else {
                    return null; // Hide if no data
                }
            }
            
            const cctv = feature.get('cctvData');
            const iconUrl = createCCTVIcon(cctv || {});
            
            return new ol.style.Style({
                image: new ol.style.Icon({
                    src: iconUrl,
                    scale: 0.75,  // Scale down slightly for better fit
                    anchor: [0.5, 1],
                    anchorXUnits: 'fraction',
                    anchorYUnits: 'fraction',
                    opacity: 1
                })
            });
        },
        zIndex: 1001  // Z-index lebih tinggi dari hazard layer
    });
    map.addLayer(cctvLayer);

    // Gunakan cctvLocationsForMap untuk map marker (hanya yang punya koordinat)
    // Sidebar menggunakan cctvLocations (semua data termasuk yang tidak punya koordinat)
    // OPTIMIZED: Batch rendering untuk CCTV markers
    function addCctvMarkersBatch() {
        if (!cctvLocationsForMap || cctvLocationsForMap.length === 0) return;
        
        const source = cctvLayer.getSource();
        const features = [];
        const BATCH_SIZE = 100;
        
        // Prepare all features first
        cctvLocationsForMap.forEach(function(cctv) {
            if (!cctv.location || !Array.isArray(cctv.location) || cctv.location.length !== 2) {
                return;
            }
            
            const feature = new ol.Feature({
                geometry: new ol.geom.Point(
                    ol.proj.fromLonLat(cctv.location)
                ),
                name: cctv.name || cctv.cctv_name || cctv.nama_cctv || 'CCTV',
                type: 'cctv',
                cctvData: cctv
            });
            features.push(feature);
        });
        
        // Batch add features
        if (features.length > 0) {
            let index = 0;
            function addBatch() {
                const batch = features.slice(index, index + BATCH_SIZE);
                if (batch.length > 0) {
                    source.addFeatures(batch);
                    index += BATCH_SIZE;
                    if (index < features.length) {
                        requestAnimationFrame(addBatch);
                    } else {
                        console.log(`Added ${features.length} CCTV markers in batches`);
                    }
                }
            }
            requestAnimationFrame(addBatch);
        }
    }
    
    // Defer CCTV marker rendering
    setTimeout(() => {
        addCctvMarkersBatch();
    }, 300);

    // OPTIMIZED: Batch rendering untuk insiden markers
    function addInsidenMarkersBatch() {
        if (!insidenDataset || insidenDataset.length === 0) return;
        
        const source = insidenLayer.getSource();
        const features = [];
        const BATCH_SIZE = 100;
        
        insidenDataset.forEach(function (insiden) {
            if (!insiden.latitude || !insiden.longitude) return;
            
            const feature = new ol.Feature({
                geometry: new ol.geom.Point(
                    ol.proj.fromLonLat([parseFloat(insiden.longitude), parseFloat(insiden.latitude)])
                ),
                type: 'insiden',
                insidenId: insiden.no_kecelakaan,
                data: insiden
            });
            features.push(feature);
        });
        
        if (features.length > 0) {
            let index = 0;
            function addBatch() {
                const batch = features.slice(index, index + BATCH_SIZE);
                if (batch.length > 0) {
                    source.addFeatures(batch);
                    index += BATCH_SIZE;
                    if (index < features.length) {
                        requestAnimationFrame(addBatch);
                    }
                }
            }
            requestAnimationFrame(addBatch);
        }
    }
    
    // Defer insiden marker rendering
    setTimeout(() => {
        addInsidenMarkersBatch();
    }, 400);

    // Create vector layer for GR (Golden Rule)
    grLayer = new ol.layer.Vector({
        source: new ol.source.Vector(),
        style: function(feature) {
            // Check site filter
            if (currentSiteFilter) {
                const grData = feature.get('data');
                if (grData) {
                    const grSite = grData.site || null;
                    if (grSite !== currentSiteFilter) {
                        return null; // Hide feature if doesn't match filter
                    }
                } else {
                    return null; // Hide if no data
                }
            }
            
            return new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 8,
                    fill: new ol.style.Fill({ color: '#8b5cf6' }), // Purple for GR
                    stroke: new ol.style.Stroke({
                        color: '#ffffff',
                        width: 2
                    })
                })
            });
        },
        zIndex: 1002  // Z-index untuk GR layer
    });
    map.addLayer(grLayer);

    // OPTIMIZED: Batch rendering untuk GR markers
    function addGrMarkersBatch() {
        if (!grDetections || !Array.isArray(grDetections)) return;
        
        const source = grLayer.getSource();
        const features = [];
        const BATCH_SIZE = 100;
        
        grDetections.forEach(function(gr) {
            if (gr.location && gr.location.lat && gr.location.lng) {
                const feature = new ol.Feature({
                    geometry: new ol.geom.Point(
                        ol.proj.fromLonLat([gr.location.lng, gr.location.lat])
                    ),
                    type: 'gr',
                    grId: gr.id,
                    data: gr
                });
                features.push(feature);
            }
        });
        
        if (features.length > 0) {
            let index = 0;
            function addBatch() {
                const batch = features.slice(index, index + BATCH_SIZE);
                if (batch.length > 0) {
                    source.addFeatures(batch);
                    index += BATCH_SIZE;
                    if (index < features.length) {
                        requestAnimationFrame(addBatch);
                    }
                }
            }
            requestAnimationFrame(addBatch);
        }
    }
    
    // Defer GR marker rendering
    setTimeout(() => {
        addGrMarkersBatch();
    }, 600);

    // Function to create vehicle unit icon
    function createVehicleUnitIcon(unit) {
        const canvas = document.createElement('canvas');
        canvas.width = 48;
        canvas.height = 48;
        const ctx = canvas.getContext('2d');
        
        // Clear canvas
        ctx.clearRect(0, 0, 48, 48);
        
        // Determine color based on vehicle type
        let color = '#3b82f6'; // Default blue
        if (unit.vehicle_type) {
            const vehicleType = unit.vehicle_type.toLowerCase();
            if (vehicleType.includes('dump') || vehicleType.includes('truck')) {
                color = '#f59e0b'; // Orange for trucks
            } else if (vehicleType.includes('prime') || vehicleType.includes('mover')) {
                color = '#10b981'; // Green for prime movers
            } else if (vehicleType.includes('lube')) {
                color = '#8b5cf6'; // Purple for lube trucks
            }
        }
        
        // Draw vehicle icon (simplified truck shape)
        ctx.save();
        ctx.translate(24, 24);
        
        // Main body
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.roundRect(-12, -8, 24, 16, 3);
        ctx.fill();
        
        // Cabin
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.roundRect(-12, -12, 8, 8, 2);
        ctx.fill();
        
        // Windows
        ctx.fillStyle = '#1e293b';
        ctx.fillRect(-10, -10, 4, 4);
        
        // Wheels
        ctx.fillStyle = '#0f172a';
        ctx.beginPath();
        ctx.arc(-6, 6, 3, 0, 2 * Math.PI);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(6, 6, 3, 0, 2 * Math.PI);
        ctx.fill();
        
        // Border
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.roundRect(-12, -12, 24, 20, 3);
        ctx.stroke();
        
        ctx.restore();
        
        return canvas.toDataURL();
    }
    
    // Create vector layer for unit vehicles
    unitVehicleLayer = new ol.layer.Vector({
        source: new ol.source.Vector(),
        visible: false,  // Default hidden untuk performa
        style: function(feature) {
            const unit = feature.get('unitData');
            if (!unit) {
                return null; // Hide if no unit data
            }
            
            const iconUrl = createVehicleUnitIcon(unit);
            const course = parseFloat(unit.course) || 0;
            
            return new ol.style.Style({
                image: new ol.style.Icon({
                    src: iconUrl,
                    scale: 0.8,
                    anchor: [0.5, 0.5],
                    anchorXUnits: 'fraction',
                    anchorYUnits: 'fraction',
                    opacity: 1,
                    rotation: course * Math.PI / 180 // Rotate based on course (convert degrees to radians)
                })
            });
        },
        zIndex: 1002  // Z-index above CCTV layer
    });
    map.addLayer(unitVehicleLayer);
    console.log('Unit vehicle layer created and added to map');

    // Function to create user GPS icon from SVG - using direct data URI
    const svgIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><g><path fill="#506C7F" d="M18,12c0-5.522,4.478-10,10-10h8c5.522,0,10,4.478,10,10v7c0-3.313-2.687-6-6-6h-6c-2.209,0-4-1.791-4-4c0-0.553-0.447-1-1-1s-1,0.447-1,1c0,2.209-1.791,4-4,4c-3.313,0-6,2.687-6,6V12z"></path><path fill="#45AAB8" d="M62,60c0,1.104-0.896,2-2,2H4c-1.104,0-2-0.896-2-2v-8c0-1.104,0.447-2.104,1.172-2.828l-0.004-0.004c4.148-3.343,8.896-5.964,14.046-7.714C20.869,45.467,26.117,48,31.973,48c5.862,0,11.115-2.538,14.771-6.56c5.167,1.75,9.929,4.376,14.089,7.728l-0.004,0.004C61.553,49.896,62,50.896,62,52V60z"></path><g><path fill="#F9EBB2" d="M32,42c-2.853,0-5.502-0.857-7.715-2.322c-1.675,0.283-3.325,0.638-4.934,1.097C22.602,43.989,27.041,46,31.973,46c4.938,0,9.383-2.017,12.634-5.238c-1.595-0.454-3.231-0.803-4.892-1.084C37.502,41.143,34.853,42,32,42z"></path><path fill="#F9EBB2" d="M46,22h-1c-0.553,0-1-0.447-1-1v-1v-1c0-2.209-1.791-4-4-4h-6c-2.088,0-3.926-1.068-5-2.687C27.926,13.932,26.088,15,24,15c-2.209,0-4,1.791-4,4v1v1c0,0.553-0.447,1-1,1h-1c-0.553,0-1,0.447-1,1v2c0,0.553,0.447,1,1,1h1c0.553,0,1,0.447,1,1v1c0,6.627,5.373,12,12,12s12-5.373,12-12v-1c0-0.553,0.447-1,1-1h1c0.553,0,1-0.447,1-1v-2C47,22.447,46.553,22,46,22z"></path></g><path fill="#394240" d="M62.242,47.758l0.014-0.014c-5.847-4.753-12.84-8.137-20.491-9.722C44.374,35.479,46,31.932,46,28c1.657,0,3-1.343,3-3v-2c0-0.886-0.391-1.673-1-2.222V12c0-6.627-5.373-12-12-12h-8c-6.627,0-12,5.373-12,12v8.778c-0.609,0.549-1,1.336-1,2.222v2c0,1.657,1.343,3,3,3c0,3.932,1.626,7.479,4.236,10.022c-7.652,1.586-14.646,4.969-20.492,9.722l0.014,0.014C0.672,48.844,0,50.344,0,52v8c0,2.211,1.789,4,4,4h56c2.211,0,4-1.789,4-4v-8C64,50.344,63.328,48.844,62.242,47.758z M18,12c0-5.522,4.478-10,10-10h8c5.522,0,10,4.478,10,10v7c0-3.313-2.687-6-6-6h-6c-2.209,0-4-1.791-4-4c0-0.553-0.447-1-1-1s-1,0.447-1,1c0,2.209-1.791,4-4,4c-3.313,0-6,2.687-6,6V12z M20,28v-1c0-0.553-0.447-1-1-1h-1c-0.553,0-1-0.447-1-1v-2c0-0.553,0.447-1,1-1h1c0.553,0,1-0.447,1-1v-2c0-2.209,1.791-4,4-4c2.088,0,3.926-1.068,5-2.687C30.074,13.932,31.912,15,34,15h6c2.209,0,4,1.791,4,4v2c0,0.553,0.447,1,1,1h1c0.553,0,1,0.447,1,1v2c0,0.553-0.447,1-1,1h-1c-0.553,0-1,0.447-1,1v1c0,6.627-5.373,12-12,12S20,34.627,20,28z M24.285,39.678C26.498,41.143,29.147,42,32,42s5.502-0.857,7.715-2.322c1.66,0.281,3.297,0.63,4.892,1.084C41.355,43.983,36.911,46,31.973,46c-4.932,0-9.371-2.011-12.621-5.226C20.96,40.315,22.61,39.961,24.285,39.678z M62,60c0,1.104-0.896,2-2,2H4c-1.104,0-2-0.896-2-2v-8c0-1.104,0.447-2.104,1.172-2.828l-0.004-0.004c4.148-3.343,8.896-5.964,14.046-7.714C20.869,45.467,26.117,48,31.973,48c5.862,0,11.115-2.538,14.771-6.56c5.167,1.75,9.929,4.376,14.089,7.728l-0.004,0.004C61.553,49.896,62,50.896,62,52V60z"></path><path fill="#394240" d="M24.537,21.862c0.475,0.255,1.073,0.068,1.345-0.396C25.91,21.419,26.18,21,26.998,21c0.808,0,1.096,0.436,1.111,0.458C28.287,21.803,28.637,22,28.999,22c0.154,0,0.311-0.035,0.457-0.111c0.491-0.253,0.684-0.856,0.431-1.347C29.592,19.969,28.651,19,26.998,19c-1.691,0-2.618,0.983-2.9,1.564C23.864,21.047,24.063,21.609,24.537,21.862z"></path><path fill="#394240" d="M34.539,21.862c0.475,0.255,1.073,0.068,1.345-0.396C35.912,21.419,36.182,21,37,21c0.808,0,1.096,0.436,1.111,0.458C38.289,21.803,38.639,22,39.001,22c0.154,0,0.311-0.035,0.457-0.111c0.491-0.253,0.684-0.856,0.431-1.347C39.594,19.969,38.653,19,37,19c-1.691,0-2.618,0.983-2.9,1.564C33.866,21.047,34.065,21.609,34.539,21.862z"></path></g></svg>';
    const userGpsIconUrl = 'data:image/svg+xml;base64,' + btoa(svgIcon);

    // Function to calculate dynamic scale based on zoom level
    function getDynamicScale(zoom) {
        // Scale dinamis berdasarkan zoom level
        // Zoom rendah (jauh) = scale kecil, Zoom tinggi (dekat) = scale besar
        if (zoom <= 10) {
            return 0.15; // Sangat kecil saat zoom jauh
        } else if (zoom <= 12) {
            return 0.2; // Kecil
        } else if (zoom <= 14) {
            return 0.3; // Sedang
        } else if (zoom <= 16) {
            return 0.4; // Agak besar
        } else if (zoom <= 18) {
            return 0.5; // Besar
        } else {
            return 0.6; // Sangat besar saat zoom sangat dekat
        }
    }

    // Create User GPS Layer
    userGpsLayer = new ol.layer.Vector({
        source: new ol.source.Vector(),
        style: function(feature, resolution) {
            const userData = feature.get('userData');
            // Dapatkan zoom level dari map
            const zoom = map.getView().getZoom();
            const dynamicScale = getDynamicScale(zoom);
            
            // Icon untuk GPS orang - menggunakan SVG dengan scale dinamis
            const iconStyle = new ol.style.Style({
                image: new ol.style.Icon({
                    anchor: [0.5, 1],
                    anchorXUnits: 'fraction',
                    anchorYUnits: 'fraction',
                    src: userGpsIconUrl,
                    scale: dynamicScale,
                    rotation: userData?.course ? (userData.course * Math.PI / 180) : 0
                })
            });
            return iconStyle;
        },
        zIndex: 100
    });
    
    // Update style saat zoom berubah
    map.getView().on('change:resolution', function() {
        if (userGpsLayer) {
            userGpsLayer.getSource().changed();
        }
    });
    map.addLayer(userGpsLayer);
    console.log('User GPS layer created and added to map');

    // Function to load user GPS data
    function loadUserGpsData() {
        fetch('{{ route("maps.api.user-gps") }}')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.users) {
                    // Deduplikasi: jika user_id sama, ambil yang terbaru berdasarkan gps_updated_at
                    const uniqueUsers = [];
                    const userMap = new Map();
                    
                    data.users.forEach(user => {
                        const userId = user.user_id || user.id;
                        if (!userId) return;
                        
                        const existingUser = userMap.get(userId);
                        if (!existingUser) {
                            // User belum ada, tambahkan
                            userMap.set(userId, user);
                            uniqueUsers.push(user);
                        } else {
                            // User sudah ada, bandingkan timestamp dan ambil yang terbaru
                            const existingTime = existingUser.gps_updated_at || existingUser.gps_created_at || '';
                            const currentTime = user.gps_updated_at || user.gps_created_at || '';
                            
                            if (currentTime > existingTime) {
                                // Replace dengan data yang lebih baru
                                const index = uniqueUsers.indexOf(existingUser);
                                if (index !== -1) {
                                    uniqueUsers[index] = user;
                                    userMap.set(userId, user);
                                }
                            }
                        }
                    });
                    
                    updateUserGpsMarkers(uniqueUsers);
                    filteredSidebarData.gps = uniqueUsers;
                    updateTabCounts();
                    // Render list jika tab GPS sedang aktif
                    if (currentSidebarTab === 'gps') {
                        renderGpsList(filteredSidebarData.gps);
                    }
                    console.log('User GPS data loaded (hari ini):', uniqueUsers.length, 'unique users (from', data.count, 'total)');
                } else {
                    console.error('Error loading user GPS data:', data.error);
                    // Tampilkan empty state jika error dan tab GPS aktif
                    if (currentSidebarTab === 'gps') {
                        const container = document.getElementById('gpsList');
                        if (container) {
                            container.innerHTML = `
                                <div class="empty-state">
                                    <i class="material-icons-outlined">person_pin</i>
                                    <p>Tidak ada data GPS Orang hari ini</p>
                                    ${data.error ? `<p style="font-size: 11px; color: #ef4444; margin-top: 8px;">${data.error}</p>` : ''}
                                </div>
                            `;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching user GPS data:', error);
                // Tampilkan error di sidebar jika tab GPS aktif
                if (currentSidebarTab === 'gps') {
                    const container = document.getElementById('gpsList');
                    if (container) {
                        container.innerHTML = `
                            <div class="empty-state">
                                <i class="material-icons-outlined">error_outline</i>
                                <p>Gagal memuat data GPS Orang</p>
                                <p style="font-size: 11px; color: #ef4444; margin-top: 8px;">Silakan refresh halaman</p>
                            </div>
                        `;
                    }
                }
            });
    }

    // Function to add/update user GPS markers
    function updateUserGpsMarkers(users) {
        if (!userGpsLayer) {
            console.warn('User GPS layer not initialized');
            return;
        }
        
        const source = userGpsLayer.getSource();
        const existingFeatures = source.getFeatures();
        const existingUsersMap = new Map();
        
        // Create map of existing features by userId
        existingFeatures.forEach(function(feature) {
            const userId = feature.get('userId');
            if (userId) {
                existingUsersMap.set(userId, feature);
            }
        });
        
        // Process new/updated users
        const processedUserIds = new Set();
        let addedCount = 0;
        let updatedCount = 0;
        let skippedCount = 0;
        
        users.forEach(function(user) {
            // Validate coordinates
            if (!user.latitude || !user.longitude || 
                user.latitude === 0 || user.longitude === 0 ||
                isNaN(parseFloat(user.latitude)) || isNaN(parseFloat(user.longitude))) {
                skippedCount++;
                return;
            }
            
            const userId = user.user_id || user.id;
            if (!userId) {
                skippedCount++;
                return;
            }
            
            processedUserIds.add(userId);
            
            // Convert coordinates to map projection
            const longitude = parseFloat(user.longitude);
            const latitude = parseFloat(user.latitude);
            const coordinate = ol.proj.fromLonLat([longitude, latitude]);
            
            // Check if feature already exists
            if (existingUsersMap.has(userId)) {
                // Update existing feature
                const feature = existingUsersMap.get(userId);
                const oldCoord = feature.getGeometry().getCoordinates();
                
                // Only update if coordinates changed
                if (oldCoord[0] !== coordinate[0] || oldCoord[1] !== coordinate[1]) {
                    feature.getGeometry().setCoordinates(coordinate);
                    updatedCount++;
                }
                
                // Always update user data
                feature.set('userData', user);
                feature.set('userId', userId);
                feature.set('type', 'user_gps');
                
                // Trigger style update
                feature.changed();
            } else {
                // Create new feature
                const feature = new ol.Feature({
                    geometry: new ol.geom.Point(coordinate),
                    type: 'user_gps',
                    userId: userId,
                    userData: user
                });
                source.addFeature(feature);
                addedCount++;
            }
        });
        
        // Remove features that no longer exist in the data
        let removedCount = 0;
        existingFeatures.forEach(function(feature) {
            const userId = feature.get('userId');
            if (userId && !processedUserIds.has(userId)) {
                source.removeFeature(feature);
                removedCount++;
            }
        });
        
        console.log('User GPS markers updated:', {
            total: processedUserIds.size,
            added: addedCount,
            updated: updatedCount,
            removed: removedCount,
            skipped: skippedCount
        });
    }

    // Initial load of user GPS data
    loadUserGpsData();
    // Refresh every 30 seconds
    setInterval(loadUserGpsData, 30000);

    // Function to add/update unit vehicle markers
    function updateUnitVehicleMarkers(units) {
        if (!unitVehicleLayer) {
            console.warn('Unit vehicle layer not initialized');
            return;
        }
        
        const source = unitVehicleLayer.getSource();
        const existingFeatures = source.getFeatures();
        const existingUnitsMap = new Map();
        
        // Create map of existing features by unitId
        existingFeatures.forEach(function(feature) {
            const unitId = feature.get('unitId');
            if (unitId) {
                existingUnitsMap.set(unitId, feature);
            }
        });
        
        // Process new/updated units
        const processedUnitIds = new Set();
        let addedCount = 0;
        let updatedCount = 0;
        let skippedCount = 0;
        
        units.forEach(function(unit) {
            // Validate coordinates
            if (!unit.latitude || !unit.longitude || 
                unit.latitude === 0 || unit.longitude === 0 ||
                isNaN(parseFloat(unit.latitude)) || isNaN(parseFloat(unit.longitude))) {
                skippedCount++;
                return;
            }
            
            const unitId = unit.unit_id || unit.id || unit.integration_id;
            if (!unitId) {
                skippedCount++;
                return;
            }
            
            processedUnitIds.add(unitId);
            
            // Convert coordinates to map projection
            const longitude = parseFloat(unit.longitude);
            const latitude = parseFloat(unit.latitude);
            const coordinate = ol.proj.fromLonLat([longitude, latitude]);
            
            // Check if feature already exists
            if (existingUnitsMap.has(unitId)) {
                // Update existing feature
                const feature = existingUnitsMap.get(unitId);
                const oldCoord = feature.getGeometry().getCoordinates();
                
                // Only update if coordinates changed
                if (oldCoord[0] !== coordinate[0] || oldCoord[1] !== coordinate[1]) {
                    feature.getGeometry().setCoordinates(coordinate);
                    updatedCount++;
                }
                
                // Always update unit data (for other properties like speed, battery, etc.)
                feature.set('unitData', unit);
                feature.set('unitId', unitId);
                
                // Trigger style update for icon rotation based on course
                feature.changed();
            } else {
                // Create new feature
                const feature = new ol.Feature({
                    geometry: new ol.geom.Point(coordinate),
                    type: 'unit_vehicle',
                    unitId: unitId,
                    unitData: unit
                });
                source.addFeature(feature);
                addedCount++;
            }
        });
        
        // Remove features that no longer exist in the data
        let removedCount = 0;
        existingFeatures.forEach(function(feature) {
            const unitId = feature.get('unitId');
            if (unitId && !processedUnitIds.has(unitId)) {
                source.removeFeature(feature);
                removedCount++;
            }
        });
        
        console.log('Unit vehicles updated:', {
            total: processedUnitIds.size,
            added: addedCount,
            updated: updatedCount,
            removed: removedCount,
            skipped: skippedCount
        });
    }

    // Initial load of unit vehicles
    console.log('Loading unit vehicles:', unitVehicles.length, 'units');
    updateUnitVehicleMarkers(unitVehicles);
    // Update filteredSidebarData.unit dengan data initial
    if (unitVehicles && unitVehicles.length > 0) {
        filteredSidebarData.unit = [...unitVehicles];
        updateTabCounts();
    }
    
    // Function to refresh unit vehicle data from server
    async function refreshUnitVehicles() {
        try {
            const response = await fetch('{{ route("maps.api.unit-vehicles") }}');
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.unitVehicles) {
                    // Update global unitVehicles array
                    unitVehicles = data.unitVehicles;
                    
                    // Update filteredSidebarData.unit untuk sidebar
                    filteredSidebarData.unit = [...(data.unitVehicles || [])];
                    
                    // Update markers on map
                    updateUnitVehicleMarkers(data.unitVehicles);
                    
                    // Update tab count
                    updateTabCounts();
                    
                    // Update unit list di sidebar jika tab unit aktif
                    if (currentSidebarTab === 'unit') {
                        renderUnitList(filteredSidebarData.unit);
                    } else {
                        // Jika tab unit tidak aktif, pastikan data sudah siap untuk ditampilkan saat user klik tab unit
                        console.log('Unit data loaded, ready for sidebar:', filteredSidebarData.unit.length, 'units');
                        // Update tab count untuk menampilkan jumlah unit yang benar
                        updateTabCounts();
                    }
                    
                    // Update unit list if unit view is active (untuk view selector lama)
                    const viewSelector = document.getElementById('viewSelector');
                    if (viewSelector && viewSelector.value === 'unit') {
                        if (typeof window.renderUnitList === 'function') {
                            window.renderUnitList();
                        }
                    }
                    
                    console.log('Unit vehicles refreshed:', data.count, 'units at', new Date().toLocaleTimeString());
                    console.log('Unit data sample:', filteredSidebarData.unit.slice(0, 3));
                } else {
                    console.warn('Failed to refresh unit vehicles:', data);
                }
            } else {
                console.error('Failed to fetch unit vehicles:', response.status, response.statusText);
            }
        } catch (error) {
            console.error('Error refreshing unit vehicles:', error);
        }
    }
    
    // Refresh unit vehicles immediately on page load
    // Tunggu sedikit untuk memastikan semua fungsi sudah terdefinisi
    setTimeout(() => {
        refreshUnitVehicles();
    }, 500);
    
    // Auto-refresh unit vehicles every 30 seconds
    let unitVehicleRefreshInterval = setInterval(refreshUnitVehicles, 30000);
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (unitVehicleRefreshInterval) {
            clearInterval(unitVehicleRefreshInterval);
        }
    });
    
    // Expose refresh function globally for manual refresh if needed
    window.refreshUnitVehicles = refreshUnitVehicles;

    // Load BMO2 PAMA layers after map is created
    setTimeout(function() {
        console.log('Loading BMO2 PAMA layers...');
        console.log('Checking available variables:');
        console.log('- areaKerjaGeoJsonDataPama:', typeof window.areaKerjaGeoJsonDataPama);
        console.log('- areaCctvGeoJsonDataBmo2Pama:', typeof window.areaCctvGeoJsonDataBmo2Pama);
        console.log('- difference_bmo2_pama:', typeof window.difference_bmo2_pama);
        console.log('- symmetrical_difference_bmo1_fad:', typeof window.symmetrical_difference_bmo1_fad);
        console.log('- intersection_bmo1_fad:', typeof window.intersection_bmo1_fad);

        // Area Kerja BMO2 PAMA
        if (typeof window.areaKerjaGeoJsonDataPama !== 'undefined' && window.areaKerjaGeoJsonDataPama) {
            try {
                areaKerjaBmo2PamaLayer = createLayerFromGeoJson(
                    window.areaKerjaGeoJsonDataPama,
                    'Area Kerja BMO2 PAMA',
                    getAreaKerjaStyle,
                    410
                );
                // Ensure layer is visible
                areaKerjaBmo2PamaLayer.setVisible(true);
                map.addLayer(areaKerjaBmo2PamaLayer);
                console.log('✓ Area Kerja BMO2 PAMA layer added, features:', areaKerjaBmo2PamaLayer.getSource().getFeatures().length);
                console.log('✓ Area Kerja BMO2 PAMA layer visible:', areaKerjaBmo2PamaLayer.getVisible());
            } catch (error) {
                console.error('Error creating Area Kerja BMO2 PAMA layer:', error);
            }
        } else {
            console.warn('✗ areaKerjaGeoJsonDataPama not found or undefined');
        }

        // Area CCTV BMO2 PAMA
        if (typeof window.areaCctvGeoJsonDataBmo2Pama !== 'undefined' && window.areaCctvGeoJsonDataBmo2Pama) {
            try {
                areaCctvBmo2PamaLayer = createLayerFromGeoJson(
                    window.areaCctvGeoJsonDataBmo2Pama,
                    'Area CCTV BMO2 PAMA',
                    getAreaCctvStyle,
                    510
                );
                // Ensure layer is visible
                areaCctvBmo2PamaLayer.setVisible(true);
                map.addLayer(areaCctvBmo2PamaLayer);
                console.log('✓ Area CCTV BMO2 PAMA layer added, features:', areaCctvBmo2PamaLayer.getSource().getFeatures().length);
                console.log('✓ Area CCTV BMO2 PAMA layer visible:', areaCctvBmo2PamaLayer.getVisible());
            } catch (error) {
                console.error('Error creating Area CCTV BMO2 PAMA layer:', error);
            }
        } else {
            console.warn('✗ areaCctvGeoJsonDataBmo2Pama not found or undefined');
        }

        // Difference BMO2 PAMA
        if (typeof window.difference_bmo2_pama !== 'undefined' && window.difference_bmo2_pama) {
            try {
                differenceBmo2PamaLayer = createLayerFromGeoJson(
                    window.difference_bmo2_pama,
                    'Difference BMO2 PAMA',
                    getDifferenceStyle,
                    350
                );
                differenceBmo2PamaLayer.setVisible(true);
                map.addLayer(differenceBmo2PamaLayer);
                console.log('✓ Difference BMO2 PAMA layer added, features:', differenceBmo2PamaLayer.getSource().getFeatures().length);
            } catch (error) {
                console.error('Error creating Difference BMO2 PAMA layer:', error);
            }
        } else {
            console.warn('✗ difference_bmo2_pama not found or undefined');
        }

        // Symmetrical Difference BMO2 PAMA
        if (typeof window.symmetrical_difference_bmo1_fad !== 'undefined' && window.symmetrical_difference_bmo1_fad) {
            try {
                // Note: Variable name is wrong in file, but data is for BMO2 PAMA
                symmetricalDifferenceBmo2PamaLayer = createLayerFromGeoJson(
                    window.symmetrical_difference_bmo1_fad,
                    'Symmetrical Difference BMO2 PAMA',
                    getSymmetricalDifferenceStyle,
                    360
                );
                symmetricalDifferenceBmo2PamaLayer.setVisible(true);
                map.addLayer(symmetricalDifferenceBmo2PamaLayer);
                console.log('✓ Symmetrical Difference BMO2 PAMA layer added, features:', symmetricalDifferenceBmo2PamaLayer.getSource().getFeatures().length);
            } catch (error) {
                console.error('Error creating Symmetrical Difference BMO2 PAMA layer:', error);
            }
        } else {
            console.warn('✗ symmetrical_difference_bmo1_fad not found or undefined');
        }

        // Intersection BMO2 PAMA
        if (typeof window.intersection_bmo1_fad !== 'undefined' && window.intersection_bmo1_fad) {
            try {
                // Note: Variable name is wrong in file, but data is for BMO2 PAMA
                intersectionBmo2PamaLayer = createLayerFromGeoJson(
                    window.intersection_bmo1_fad,
                    'Intersection BMO2 PAMA',
                    getIntersectionStyle,
                    370
                );
                intersectionBmo2PamaLayer.setVisible(true);
                map.addLayer(intersectionBmo2PamaLayer);
                console.log('✓ Intersection BMO2 PAMA layer added, features:', intersectionBmo2PamaLayer.getSource().getFeatures().length);
            } catch (error) {
                console.error('Error creating Intersection BMO2 PAMA layer:', error);
            }
        } else {
            console.warn('✗ intersection_bmo1_fad not found or undefined');
        }
        
        // Ensure all area kerja layers are visible by default
        if (areaKerjaBmo2PamaLayer) {
            areaKerjaBmo2PamaLayer.setVisible(true);
            console.log('✓ Area Kerja BMO2 PAMA layer set to visible');
        }
        if (areaCctvBmo2PamaLayer) {
            areaCctvBmo2PamaLayer.setVisible(true);
            console.log('✓ Area CCTV BMO2 PAMA layer set to visible');
        }
        if (differenceBmo2PamaLayer) {
            differenceBmo2PamaLayer.setVisible(true);
        }
        if (symmetricalDifferenceBmo2PamaLayer) {
            symmetricalDifferenceBmo2PamaLayer.setVisible(true);
        }
        if (intersectionBmo2PamaLayer) {
            intersectionBmo2PamaLayer.setVisible(true);
        }
        
        console.log('Finished loading BMO2 PAMA layers - All area kerja layers are visible');

        if (areaCctvBmo2PamaLayer && intersectionBmo2PamaLayer) {
            try {
                populateCoverageTable();
            } catch (error) {
                console.error('Error populating coverage table:', error);
            }
        }
        
        // Load all Area CCTV and Area Kerja layers
        // Wait for proj4js to be available
        function loadAllAreaLayers() {
            if (typeof proj4 === 'undefined') {
                console.warn('proj4js not loaded yet, retrying in 200ms...');
                setTimeout(loadAllAreaLayers, 200);
                return;
            }
            
            // Verify OpenLayers is available
            if (typeof ol === 'undefined' || typeof ol.proj === 'undefined') {
                console.warn('OpenLayers not loaded yet, retrying in 200ms...');
                setTimeout(loadAllAreaLayers, 200);
                return;
            }
            
            // Verify map is available
            if (typeof map === 'undefined' || !map) {
                console.warn('Map not available yet, retrying in 200ms...');
                setTimeout(loadAllAreaLayers, 200);
                return;
            }
            
            console.log('proj4, OpenLayers, and map are available, starting to load layers...');
            console.log('Loading all Area CCTV and Area Kerja layers...');
            
            // Store all layers in arrays for easy management
            const areaCctvLayers = [];
            const areaKerjaLayers = [];
            
            // Area CCTV Layers
            const areaCctvConfigs = [
                { varName: 'areaCctvBmo1Fad', layerName: 'Area CCTV BMO1 FAD', zIndex: 511 },
                { varName: 'areaCctvBmo1Kdc', layerName: 'Area CCTV BMO1 KDC', zIndex: 512 },
                { varName: 'areaCctvBmo2Buma', layerName: 'Area CCTV BMO2 BUMA', zIndex: 513 },
                { varName: 'areaCctvBmo2Pama', layerName: 'Area CCTV BMO2 PAMA', zIndex: 513 },
                { varName: 'areaCctvBmo3', layerName: 'Area CCTV BMO3 BAR', zIndex: 514 },
                { varName: 'areaCctvGmoKdc', layerName: 'Area CCTV GMO KDC', zIndex: 515 },
                { varName: 'areaCctvGmoPama', layerName: 'Area CCTV GMO PAMA', zIndex: 516 },
                { varName: 'areaCctvLmoBuma', layerName: 'Area CCTV LMO BUMA', zIndex: 517 },
                { varName: 'areaCctvLmoFad', layerName: 'Area CCTV LMO FAD', zIndex: 517 },
                { varName: 'areaCctvSmoMtn', layerName: 'Area CCTV SMO MTN', zIndex: 518 }
            ];
            
            areaCctvConfigs.forEach(config => {
                if (typeof window[config.varName] !== 'undefined' && window[config.varName]) {
                    try {
                        console.log(`Loading ${config.layerName}...`);
                        console.log(`Data check - ${config.varName}:`, {
                            hasData: !!window[config.varName],
                            featuresCount: window[config.varName].features ? window[config.varName].features.length : 0,
                            crs: window[config.varName].crs
                        });
                        const layer = createLayerFromGeoJson32650(
                            window[config.varName],
                            config.layerName,
                            getAreaCctvStyle,
                            config.zIndex
                        );
                        if (layer) {
                            const featureCount = layer.getSource().getFeatures().length;
                            console.log(`Layer created for ${config.layerName}, features:`, featureCount);
                            
                            if (featureCount > 0) {
                                layer.setVisible(true);
                                layer.setOpacity(1.0);
                                layer.setZIndex(config.zIndex);
                                map.addLayer(layer);
                                areaCctvLayers.push(layer);
                                
                                // Log layer details
                                const extent = layer.getSource().getExtent();
                                console.log(`✓ ${config.layerName} layer added successfully:`, {
                                    features: featureCount,
                                    visible: layer.getVisible(),
                                    opacity: layer.getOpacity(),
                                    zIndex: layer.getZIndex(),
                                    extent: extent
                                });
                            } else {
                                console.warn(`⚠ ${config.layerName} layer created but has no features`);
                            }
                        } else {
                            console.error(`✗ Failed to create layer for ${config.layerName}`);
                        }
                    } catch (error) {
                        console.error(`Error creating ${config.layerName} layer:`, error);
                        console.error('Error stack:', error.stack);
                        console.error('Data sample:', JSON.stringify(window[config.varName]).substring(0, 500));
                    }
                } else {
                    console.warn(`✗ ${config.varName} not found or undefined`);
                }
            });
            
            // Area Kerja Layers
            const areaKerjaConfigs = [
                { varName: 'areaKerjaBmo1Fad', layerName: 'Area Kerja BMO1 FAD', zIndex: 411 },
                { varName: 'areaKerjaBmo1Kdc', layerName: 'Area Kerja BMO1 KDC', zIndex: 412 },
                { varName: 'areaKerjaBmo2Buma', layerName: 'Area Kerja BMO2 BUMA', zIndex: 413 },
                { varName: 'areaKerjaBmo3Bar', layerName: 'Area Kerja BMO3 BAR', zIndex: 414 },
                { varName: 'areaKerjaGmoKdc', layerName: 'Area Kerja GMO KDC', zIndex: 415 },
                { varName: 'areaKerjaGmoPama', layerName: 'Area Kerja GMO PAMA', zIndex: 415 },
                { varName: 'areaKerjaLmoBuma', layerName: 'Area Kerja LMO BUMA', zIndex: 416 },
                { varName: 'areaKerjaLmoFad', layerName: 'Area Kerja LMO FAD', zIndex: 417 },
                { varName: 'areaKerjaSmoMtn', layerName: 'Area Kerja SMO MTN', zIndex: 418 }
            ];
            
            areaKerjaConfigs.forEach(config => {
                if (typeof window[config.varName] !== 'undefined' && window[config.varName]) {
                    try {
                        console.log(`Loading ${config.layerName}...`);
                        console.log(`Data check - ${config.varName}:`, {
                            hasData: !!window[config.varName],
                            featuresCount: window[config.varName].features ? window[config.varName].features.length : 0,
                            crs: window[config.varName].crs
                        });
                        const layer = createLayerFromGeoJson32650(
                            window[config.varName],
                            config.layerName,
                            getAreaKerjaStyle,
                            config.zIndex
                        );
                        if (layer) {
                            const featureCount = layer.getSource().getFeatures().length;
                            console.log(`Layer created for ${config.layerName}, features:`, featureCount);
                            
                            if (featureCount > 0) {
                                layer.setVisible(true);
                                layer.setOpacity(1.0);
                                layer.setZIndex(config.zIndex);
                                map.addLayer(layer);
                                areaKerjaLayers.push(layer);
                                
                                // Log layer details
                                const extent = layer.getSource().getExtent();
                                console.log(`✓ ${config.layerName} layer added successfully:`, {
                                    features: featureCount,
                                    visible: layer.getVisible(),
                                    opacity: layer.getOpacity(),
                                    zIndex: layer.getZIndex(),
                                    extent: extent
                                });
                            } else {
                                console.warn(`⚠ ${config.layerName} layer created but has no features`);
                            }
                        } else {
                            console.error(`✗ Failed to create layer for ${config.layerName}`);
                        }
                    } catch (error) {
                        console.error(`Error creating ${config.layerName} layer:`, error);
                        console.error('Error stack:', error.stack);
                        console.error('Data sample:', JSON.stringify(window[config.varName]).substring(0, 500));
                    }
                } else {
                    console.warn(`✗ ${config.varName} not found or undefined`);
                }
            });
            
            // Store layers globally for potential future use
            window.areaCctvLayers = areaCctvLayers;
            window.areaKerjaLayers = areaKerjaLayers;
            
            console.log(`Finished loading all layers - ${areaCctvLayers.length} Area CCTV layers, ${areaKerjaLayers.length} Area Kerja layers`);
            
            // Fit map to show all layers if there are any
            if (areaCctvLayers.length > 0 || areaKerjaLayers.length > 0) {
                const allLayers = [...areaCctvLayers, ...areaKerjaLayers];
                const extent = ol.extent.createEmpty();
                allLayers.forEach(layer => {
                    const source = layer.getSource();
                    if (source && source.getExtent) {
                        ol.extent.extend(extent, source.getExtent());
                    }
                });
                if (!ol.extent.isEmpty(extent)) {
                    map.getView().fit(extent, {
                        padding: [50, 50, 50, 50],
                        maxZoom: 18
                    });
                }
            }
        }
        
        // OPTIMIZED: Defer loading GeoJSON layers untuk performa
        // Load setelah marker selesai di-render
        // OPTIMIZED: Defer loading GeoJSON layers untuk performa
        // Load setelah marker selesai di-render dan user interaction
        console.log('Scheduling loadAllAreaLayers (deferred for performance)...');
        
        // Load GeoJSON layers setelah idle atau setelah 3 detik
        let loadTimeout = setTimeout(function() {
            console.log('Calling loadAllAreaLayers now (timeout)...');
            loadAllAreaLayers();
        }, 3000);
        
        // Cancel timeout jika user berinteraksi dengan map (zoom/pan)
        map.getView().on('change:resolution', function() {
            if (loadTimeout) {
                clearTimeout(loadTimeout);
                loadTimeout = null;
            }
        });
        
        // Load saat browser idle (jika supported)
        if ('requestIdleCallback' in window) {
            requestIdleCallback(function() {
                if (loadTimeout) {
                    clearTimeout(loadTimeout);
                    loadTimeout = null;
                }
                console.log('Calling loadAllAreaLayers now (idle)...');
                loadAllAreaLayers();
            }, { timeout: 3000 });
        } // Delay lebih lama untuk prioritize marker rendering
    }, 500);

    // Function to format area in square meters or hectares
    function formatArea(areaM2) {
        if (!areaM2 || areaM2 === 0) return '0 m²';
        if (areaM2 >= 10000) {
            const hectares = areaM2 / 10000;
            return `${hectares.toFixed(2)} Ha`;
        } else {
            return `${areaM2.toFixed(2)} m²`;
        }
    }

    // Function to populate coverage table
    let coverageTableData = [];
    let filteredCoverageTableData = [];
    let currentCoveragePage = 1;
    const coveragePerPage = 10;
    let totalCoverageStats = {
        totalAreaKerja: 0,
        totalCoveredArea: 0,
        coveragePercentage: 0
    };

    function populateCoverageTable() {
        const coverageTableBody = document.getElementById('coverageTableBody');
        if (!coverageTableBody) {
            console.warn('coverageTableBody element not found, skipping populateCoverageTable');
            return;
        }
        
        if (!intersectionBmo2PamaLayer || !areaKerjaBmo2PamaLayer) {
            coverageTableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Data belum tersedia</td>
                </tr>
            `;
            return;
        }

        const intersections = intersectionBmo2PamaLayer.getSource().getFeatures();
        const areaKerjaFeatures = areaKerjaBmo2PamaLayer.getSource().getFeatures();
        
        // Calculate total area kerja using luasan from properties
        let totalAreaKerja = 0;
        areaKerjaFeatures.forEach(function(feature) {
            const luasan = feature.get('luasan');
            if (luasan && !isNaN(luasan)) {
                totalAreaKerja += parseFloat(luasan);
            }
        });
        
        // Calculate total covered area (from intersections) using luasan from properties
        let totalCoveredArea = 0;
        intersections.forEach(function(intersection) {
            const props = intersection.getProperties();
            const luasan = props.luasan || intersection.get('luasan');
            if (luasan && !isNaN(luasan)) {
                totalCoveredArea += parseFloat(luasan);
            }
        });
        
        // Calculate coverage percentage
        const coveragePercentage = totalAreaKerja > 0 ? (totalCoveredArea / totalAreaKerja) * 100 : 0;
        
        // Store total coverage stats
        totalCoverageStats = {
            totalAreaKerja: totalAreaKerja,
            totalCoveredArea: totalCoveredArea,
            coveragePercentage: coveragePercentage
        };
        
        // Group intersections by area kerja (id_lokasi)
        const coverageMap = new Map();
        
        intersections.forEach(function(intersection) {
            const props = intersection.getProperties();
            const idLokasi = props.id_lokasi;
            const lokasi = props.lokasi || 'Unknown';
            const nomorCctv = props.nomor_cctv || props.no_cctv || 'N/A';
            const namaCctv = props.nama_cctv || 'N/A';
            
            if (!idLokasi) return;
            
            // Get area from luasan property instead of calculating from geometry
            const luasan = props.luasan || intersection.get('luasan');
            const area = (luasan && !isNaN(luasan)) ? parseFloat(luasan) : 0;
            
            if (!coverageMap.has(idLokasi)) {
                // Find corresponding area kerja feature
                const areaKerjaFeature = areaKerjaFeatures.find(function(f) {
                    return f.get('id_lokasi') === idLokasi || f.get('lokasi') === lokasi;
                });
                
                // Get area kerja area from luasan property
                let areaKerjaArea = 0;
                if (areaKerjaFeature) {
                    const areaKerjaLuasan = areaKerjaFeature.get('luasan');
                    if (areaKerjaLuasan && !isNaN(areaKerjaLuasan)) {
                        areaKerjaArea = parseFloat(areaKerjaLuasan);
                    }
                }
                
                coverageMap.set(idLokasi, {
                    idLokasi: idLokasi,
                    lokasi: lokasi,
                    areaKerjaNama: areaKerjaFeature ? (areaKerjaFeature.get('lokasi') || areaKerjaFeature.get('nama') || lokasi) : lokasi,
                    areaKerjaArea: areaKerjaArea,
                    cctvList: [],
                    totalArea: 0
                });
            }
            
            const coverage = coverageMap.get(idLokasi);
            coverage.cctvList.push({
                nomor: nomorCctv,
                nama: namaCctv,
                area: area
            });
            coverage.totalArea += area;
        });
        
        // Convert to array and sort, calculate coverage percentage for each item
        coverageTableData = Array.from(coverageMap.values()).map(function(item, index) {
            const coveragePercentage = item.areaKerjaArea > 0 ? (item.totalArea / item.areaKerjaArea) * 100 : 0;
            return {
                ...item,
                index: index + 1,
                cctvCount: item.cctvList.length,
                cctvNames: item.cctvList.map(c => c.nomor).join(', '),
                coveragePercentage: coveragePercentage
            };
        });
        
        // Initialize filtered data
        filteredCoverageTableData = [...coverageTableData];
        
        // Show button if data available
        const btnShowDetail = document.getElementById('btnShowDetail');
        if (coverageTableData.length > 0 && btnShowDetail) {
            btnShowDetail.style.display = 'block';
        }
        
        renderCoverageTable();
    }

    function renderCoverageTable() {
        const tbody = document.getElementById('coverageTableBody');
        
        if (coverageTableData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Tidak ada data coverage</td>
                </tr>
            `;
            return;
        }
        
        // Filter data untuk area (B8) Rest area dan (B8) CPP saja
        const targetAreas = ['(B8) Rest area', '(B8) CPP'];
        const itemsToShow = coverageTableData.filter(function(item) {
            const areaName = item.areaKerjaNama || item.lokasi || '';
            return targetAreas.some(function(targetArea) {
                return areaName.includes(targetArea);
            });
        });
        
        if (itemsToShow.length > 0) {
            tbody.innerHTML = itemsToShow.map(function(item) {
                const statusBadge = item.cctvCount > 0 
                    ? '<span class="badge bg-success">Covered</span>' 
                    : '<span class="badge bg-warning">Partial</span>';
                
                return `
                    <tr>
                        <td colspan="5" class="py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <strong class="text-primary">${item.areaKerjaNama || item.lokasi}</strong>
                                        ${statusBadge}
                                    </div>
                                    <div class="text-muted small">
                                        <div><strong>CCTV Coverage:</strong> ${item.cctvNames || 'N/A'}</div>
                                        <div><strong>Luasan:</strong> ${formatArea(item.totalArea)}</div>
                                        <div><strong>Total CCTV:</strong> ${item.cctvCount} unit</div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Data untuk area (B8) Rest area dan (B8) CPP tidak ditemukan</td>
                </tr>
            `;
        }
    }
    
    function renderCoverageDetailTable() {
        const tbody = document.getElementById('coverageDetailTableBody');
        const pagination = document.getElementById('coverageDetailPagination');
        const summaryStatsContainer = document.getElementById('coverageSummaryStats');
        
        // Render summary stats
        if (summaryStatsContainer && totalCoverageStats) {
            const stats = totalCoverageStats;
            const coveragePercentage = stats.coveragePercentage || 0;
            let percentageColor = 'success';
            if (coveragePercentage < 50) {
                percentageColor = 'danger';
            } else if (coveragePercentage < 80) {
                percentageColor = 'warning';
            }
            
            const bgColorClass = 'bg-' + percentageColor + ' bg-opacity-10';
            const textColorClass = 'text-' + percentageColor;
            const badgeClass = 'badge bg-' + percentageColor + ' fs-6';
            
            summaryStatsContainer.innerHTML = `
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                        <i class="material-icons-outlined text-primary" style="font-size: 32px;">location_on</i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block">Total Luasan Area Kerja</small>
                                        <h5 class="mb-0 fw-bold">${formatArea(stats.totalAreaKerja)}</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                        <i class="material-icons-outlined text-success" style="font-size: 32px;">videocam</i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block">Total Tercover CCTV</small>
                                        <h5 class="mb-0 fw-bold">${formatArea(stats.totalCoveredArea)}</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="${bgColorClass} rounded-circle p-3">
                                        <i class="material-icons-outlined ${textColorClass}" style="font-size: 32px;">percent</i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block">Persentase Coverage</small>
                                        <h5 class="mb-0 fw-bold">
                                            <span class="${badgeClass}">${coveragePercentage.toFixed(2)}%</span>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        if (filteredCoverageTableData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Tidak ada data yang sesuai dengan pencarian</td>
                </tr>
            `;
            pagination.innerHTML = '';
            return;
        }
        
        const totalPages = Math.ceil(filteredCoverageTableData.length / coveragePerPage);
        const startIndex = (currentCoveragePage - 1) * coveragePerPage;
        const endIndex = startIndex + coveragePerPage;
        const pageData = filteredCoverageTableData.slice(startIndex, endIndex);
        
        tbody.innerHTML = pageData.map(function(item, idx) {
            const rowNum = startIndex + idx + 1;
            const statusBadge = item.cctvCount > 0 
                ? '<span class="badge bg-success">Covered</span>' 
                : '<span class="badge bg-warning">Partial</span>';
            
            // Calculate coverage percentage for this item
            const itemCoveragePercentage = item.coveragePercentage || 0;
            let percentageColor = 'success';
            if (itemCoveragePercentage < 50) {
                percentageColor = 'danger';
            } else if (itemCoveragePercentage < 80) {
                percentageColor = 'warning';
            }
            const percentageBadgeClass = 'badge bg-' + percentageColor;
            const percentageBadge = '<span class="' + percentageBadgeClass + '">' + itemCoveragePercentage.toFixed(2) + '%</span>';
            
            return `
                <tr>
                    <td>${rowNum}</td>
                    <td><strong>${item.areaKerjaNama || item.lokasi}</strong></td>
                    <td>
                        <small>${item.cctvNames || 'N/A'}</small>
                        ${item.cctvCount > 1 ? `<br><span class="badge bg-info bg-opacity-10 text-info">${item.cctvCount} CCTV</span>` : ''}
                    </td>
                    <td>${formatArea(item.totalArea)}</td>
                    <td>${percentageBadge}</td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        }).join('');
        
        // Render pagination
        if (totalPages > 1) {
            let paginationHTML = '';
            
            // Previous button
            paginationHTML += `
                <li class="page-item ${currentCoveragePage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="javascript:void(0)" onclick="changeCoveragePage(${currentCoveragePage - 1})">Previous</a>
                </li>
            `;
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentCoveragePage - 1 && i <= currentCoveragePage + 1)) {
                    paginationHTML += `
                        <li class="page-item ${i === currentCoveragePage ? 'active' : ''}">
                            <a class="page-link" href="javascript:void(0)" onclick="changeCoveragePage(${i})">${i}</a>
                        </li>
                    `;
                } else if (i === currentCoveragePage - 2 || i === currentCoveragePage + 2) {
                    paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }
            
            // Next button
            paginationHTML += `
                <li class="page-item ${currentCoveragePage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="javascript:void(0)" onclick="changeCoveragePage(${currentCoveragePage + 1})">Next</a>
                </li>
            `;
            
            pagination.innerHTML = paginationHTML;
        } else {
            pagination.innerHTML = '';
        }
    }

    function changeCoveragePage(page) {
        const totalPages = Math.ceil(filteredCoverageTableData.length / coveragePerPage);
        if (page < 1 || page > totalPages) return;
        currentCoveragePage = page;
        renderCoverageDetailTable();
    }
    
    function openCoverageModal() {
        // Reset to first page and clear search
        currentCoveragePage = 1;
        document.getElementById('coverageSearchInput').value = '';
        filteredCoverageTableData = [...coverageTableData];
        
        // Render table in modal
        renderCoverageDetailTable();
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('coverageDetailModal'));
        modal.show();
    }
    
    function filterCoverageTable() {
        const searchTerm = document.getElementById('coverageSearchInput').value.toLowerCase().trim();
        
        if (!searchTerm) {
            filteredCoverageTableData = [...coverageTableData];
        } else {
            filteredCoverageTableData = coverageTableData.filter(function(item) {
                const areaKerja = (item.areaKerjaNama || item.lokasi || '').toLowerCase();
                const cctvNames = (item.cctvNames || '').toLowerCase();
                return areaKerja.includes(searchTerm) || cctvNames.includes(searchTerm);
            });
        }
        
        currentCoveragePage = 1; // Reset to first page
        renderCoverageDetailTable();
    }
    
    function clearCoverageSearch() {
        document.getElementById('coverageSearchInput').value = '';
        filteredCoverageTableData = [...coverageTableData];
        currentCoveragePage = 1;
        renderCoverageDetailTable();
    }
    
    // Make functions globally accessible
    window.changeCoveragePage = changeCoveragePage;
    window.openCoverageModal = openCoverageModal;
    window.filterCoverageTable = filterCoverageTable;
    window.clearCoverageSearch = clearCoverageSearch;

    // Toggle GeoJSON layers visibility (only if elements exist)
    const showAreaKerjaBmo2Pama = document.getElementById('showAreaKerjaBmo2Pama');
    if (showAreaKerjaBmo2Pama) {
        showAreaKerjaBmo2Pama.addEventListener('change', function(e) {
            if (areaKerjaBmo2PamaLayer) {
                areaKerjaBmo2PamaLayer.setVisible(e.target.checked);
            }
        });
    }

    const showAreaCctvBmo2Pama = document.getElementById('showAreaCctvBmo2Pama');
    if (showAreaCctvBmo2Pama) {
        showAreaCctvBmo2Pama.addEventListener('change', function(e) {
            if (areaCctvBmo2PamaLayer) {
                areaCctvBmo2PamaLayer.setVisible(e.target.checked);
            }
        });
    }

    const showDifferenceBmo2Pama = document.getElementById('showDifferenceBmo2Pama');
    if (showDifferenceBmo2Pama) {
        showDifferenceBmo2Pama.addEventListener('change', function(e) {
            if (differenceBmo2PamaLayer) {
                differenceBmo2PamaLayer.setVisible(e.target.checked);
            }
        });
    }

    const showSymmetricalDifferenceBmo2Pama = document.getElementById('showSymmetricalDifferenceBmo2Pama');
    if (showSymmetricalDifferenceBmo2Pama) {
        showSymmetricalDifferenceBmo2Pama.addEventListener('change', function(e) {
            if (symmetricalDifferenceBmo2PamaLayer) {
                symmetricalDifferenceBmo2PamaLayer.setVisible(e.target.checked);
            }
        });
    }

    const showIntersectionBmo2Pama = document.getElementById('showIntersectionBmo2Pama');
    if (showIntersectionBmo2Pama) {
        showIntersectionBmo2Pama.addEventListener('change', function(e) {
            if (intersectionBmo2PamaLayer) {
                intersectionBmo2PamaLayer.setVisible(e.target.checked);
            }
        });
    }

    // Popup overlay
    const popupElement = document.getElementById('popup');
    // Site filter
    // siteFilter sudah diganti dengan mainFilterSiteBtn (dropdown button)
    // const siteFilter = document.getElementById('siteFilter');
    // currentSiteFilter sudah didefinisikan di atas
    popupOverlay = new ol.Overlay({
        element: popupElement,
        autoPan: {
            animation: {
                duration: 250
            }
        }
    });
    map.addOverlay(popupOverlay);

    // Popup closer
    const popupCloser = document.getElementById('popup-closer');
    popupCloser.onclick = function() {
        popupOverlay.setPosition(undefined);
        popupCloser.blur();
        return false;
    };

    // Click handler
    map.on('singleclick', function(evt) {
        const feature = map.forEachFeatureAtPixel(evt.pixel, function(feature) {
            return feature;
        });

        if (feature) {
            const featureType = feature.get('type');
            if (featureType === 'insiden') {
                const data = feature.get('data');
                showInsidenPopup(evt.coordinate, data);
                return;
            }
            
            // Check if it's a unit vehicle marker
            if (featureType === 'unit_vehicle') {
                const unitData = feature.get('unitData');
                showUnitVehiclePopup(evt.coordinate, unitData);
                return;
            }
            
            // Check if it's a user GPS marker
            if (featureType === 'user_gps') {
                const userData = feature.get('userData');
                showUserGpsPopup(evt.coordinate, userData);
                return;
            }
            
            // Check if it's a CCTV marker
            if (featureType === 'cctv') {
                const cctv = feature.get('cctvData');
                if (cctv) {
                    showCCTVPopup(evt.coordinate, cctv);
                }
                return;
            }
            
            // Check if it's a hazard/SAP
            const data = feature.get('data');
            if (data) {
                // Clear area kerja highlight when clicking hazard/SAP
                if (highlightedAreaKerjaLayer) {
                    map.removeLayer(highlightedAreaKerjaLayer);
                    highlightedAreaKerjaLayer = null;
                }
                // Check if it's SAP data (has task_number or jenis_laporan)
                if (data.task_number || data.jenis_laporan) {
                    showSapPopup(evt.coordinate, data);
                } else {
                    showHazardPopup(evt.coordinate, data);
                }
                return;
            }
            
            // Check if it's a GeoJSON polygon (Area Kerja or Area CCTV)
            const props = feature.getProperties();
            
            // Check for Area CCTV (has nomor_cctv property, even if null)
            const hasNomorCctv = 'nomor_cctv' in props;
            // Check for Area Kerja (has id_lokasi property)
            const hasIdLokasi = 'id_lokasi' in props;
            
            if (hasNomorCctv || hasIdLokasi) {
                let content = '';
                
                if (hasNomorCctv) {
                    // Area CCTV - Tambahkan tombol untuk filter SAP
                    const cctvNo = (props.nomor_cctv && props.nomor_cctv !== null && props.nomor_cctv !== 'null') ? props.nomor_cctv : 'N/A';
                    const cctvName = (props.nama_cctv && props.nama_cctv !== null && props.nama_cctv !== 'null') ? props.nama_cctv : 'N/A';
                    const cctvLokasi = props.coverage_lokasi || props.lokasi_pemasangan || props.coverage_detail_lokasi || props.lokasi || '';
                    const site = props.site || 'N/A';
                    const perusahaan = props.perusahaan_cctv || props.perusahaan || 'N/A';
                    const luasan = props.luasan ? props.luasan.toLocaleString('id-ID', {maximumFractionDigits: 2}) : 'N/A';
                    
                    content = `
                        <div style="min-width: 280px;">
                            <h6 style="margin: 0 0 10px 0;">Area CCTV</h6>
                            <p style="margin: 5px 0; font-size: 13px;"><strong>Nomor CCTV:</strong> ${cctvNo}</p>
                            <p style="margin: 5px 0; font-size: 13px;"><strong>Nama CCTV:</strong> ${cctvName}</p>
                            <p style="margin: 5px 0; font-size: 13px;"><strong>Lokasi:</strong> ${cctvLokasi || 'N/A'}</p>
                            <p style="margin: 5px 0; font-size: 13px;"><strong>Site:</strong> ${site}</p>
                            <p style="margin: 5px 0; font-size: 13px;"><strong>Perusahaan:</strong> ${perusahaan}</p>
                            <p style="margin: 5px 0; font-size: 13px;"><strong>Luasan:</strong> ${luasan} m²</p>
                            <hr style="margin: 10px 0;">
                            ${cctvNo !== 'N/A' ? `
                            <button class="btn btn-sm btn-primary w-100 mt-2" onclick="filterSapByAreaCctv('${cctvNo}', '${String(cctvName).replace(/'/g, "\\'")}', '${String(cctvLokasi).replace(/'/g, "\\'")}')">
                                <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">filter_list</i> Filter SAP di Area Ini
                            </button>
                            <button class="btn btn-sm btn-success w-100 mt-2" onclick="loadEvaluationSummary('area_cctv', null, null, '${cctvNo}', '${String(cctvName).replace(/'/g, "\\'")}')">
                                <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">assessment</i> Lihat Evaluasi
                            </button>
                            ` : ''}
                        </div>
                    `;
                } else if (hasIdLokasi) {
                    // Area Kerja - Tambahkan tombol untuk filter SAP
                    // Debug: log properties
                    console.log('Area Kerja properties:', props);
                    
                    // Handle null values properly - check for null, undefined, empty string, and string 'null'
                    const getValue = (val) => {
                        if (val === null || val === undefined || val === '' || val === 'null') {
                            return null;
                        }
                        return val;
                    };
                    
                    const areaKerjaId = getValue(props.id_lokasi);
                    const lokasiName = getValue(props.lokasi);
                    const site = getValue(props.site);
                    const perusahaan = getValue(props.perusahaan);
                    const areaKerja = getValue(props.area_kerja);
                    const luasan = props.luasan && props.luasan !== null && !isNaN(props.luasan)
                        ? parseFloat(props.luasan).toLocaleString('id-ID', {maximumFractionDigits: 2})
                        : 'N/A';
                    
                    content = `
                        <div style="min-width: 280px;">
                            <h6 style="margin: 0 0 10px 0;">Area Kerja</h6>
                            <p style="margin: 5px 0; font-size: 13px;"><strong>Lokasi:</strong> ${lokasiName || 'N/A'}</p>
                            ${areaKerjaId ? `<p style="margin: 5px 0; font-size: 13px;"><strong>ID Lokasi:</strong> ${areaKerjaId}</p>` : ''}
                            <p style="margin: 5px 0; font-size: 13px;"><strong>Site:</strong> ${site || 'N/A'}</p>
                            <p style="margin: 5px 0; font-size: 13px;"><strong>Perusahaan:</strong> ${perusahaan || 'N/A'}</p>
                            <p style="margin: 5px 0; font-size: 13px;"><strong>Area Kerja:</strong> ${areaKerja || 'N/A'}</p>
                            <p style="margin: 5px 0; font-size: 13px;"><strong>Luasan:</strong> ${luasan} m²</p>
                            ${areaKerjaId ? `
                            <hr style="margin: 10px 0;">
                            <button class="btn btn-sm btn-primary w-100 mt-2" onclick="filterSapByAreaKerja('${areaKerjaId}', '${String(lokasiName || '').replace(/'/g, "\\'")}')">
                                <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">filter_list</i> Filter SAP di Area Ini
                            </button>
                            <button class="btn btn-sm btn-success w-100 mt-2" onclick="loadEvaluationSummary('area_kerja', '${areaKerjaId}', '${String(lokasiName || '').replace(/'/g, "\\'")}', null, null)">
                                <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">assessment</i> Lihat Evaluasi
                            </button>
                            ` : ''}
                        </div>
                    `;
                }
                
                document.getElementById('popup-content').innerHTML = content;
                popupOverlay.setPosition(evt.coordinate);
            }
        } else {
            // Clear highlight when clicking on empty area
            if (highlightedAreaKerjaLayer) {
                map.removeLayer(highlightedAreaKerjaLayer);
                highlightedAreaKerjaLayer = null;
            }
            popupOverlay.setPosition(undefined);
        }
    });

    function showHazardPopup(coordinate, hazard) {
        // Check if it's SAP data (has task_number or jenis_laporan)
        if (hazard.task_number || hazard.jenis_laporan) {
            showSapPopup(coordinate, hazard);
            return;
        }
        
        const content = `
            <div style="min-width: 200px;">
                <h6 style="margin: 0 0 10px 0;">${hazard.type}</h6>
                <p style="margin: 5px 0; font-size: 13px;">${hazard.description}</p>
                <p style="margin: 5px 0; font-size: 12px; color: #666;">
                    <strong>Severity:</strong> ${hazard.severity}<br>
                    <strong>Status:</strong> ${hazard.status}<br>
                    <strong>Lokasi:</strong> ${hazard.zone || 'Unknown'}<br>
                    <strong>Detected At:</strong> ${hazard.detected_at || 'N/A'}<br>
                    <strong>CCTV ID:</strong> ${hazard.cctv_id || 'N/A'}
                </p>
            </div>
        `;
        document.getElementById('popup-content').innerHTML = content;
        popupOverlay.setPosition(coordinate);
    }
    
    function showSapPopup(coordinate, sap) {
        const taskNumber = sap.task_number || 'N/A';
        const jenisLaporan = sap.jenis_laporan || 'N/A';
        const lokasi = sap.lokasi || 'N/A';
        const escapedTaskNumber = taskNumber.replace(/"/g, '&quot;');
        
        // Format tanggal dengan mengurangi 7 jam
        let tanggalFormatted = 'N/A';
        if (sap.tanggal_pelaporan || sap.detected_at) {
            try {
                const date = new Date(sap.tanggal_pelaporan || sap.detected_at);
                // Kurangi 7 jam dari waktu database
                date.setHours(date.getHours() - 7);
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                tanggalFormatted = `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
            } catch (e) {
                tanggalFormatted = sap.tanggal_pelaporan || sap.detected_at || 'N/A';
            }
        }
        
        const content = `
            <div style="min-width: 250px;">
                <h6 style="margin: 0 0 10px 0; color: #3b82f6;">${jenisLaporan}</h6>
                <p style="margin: 5px 0; font-size: 13px;">
                    <strong>Task Number:</strong> ${taskNumber}<br>
                    <strong>Aktivitas:</strong> ${sap.aktivitas_pekerjaan || 'N/A'}<br>
                    <strong>Lokasi:</strong> ${lokasi}<br>
                    <strong>Tanggal:</strong> ${tanggalFormatted}
                </p>
                <button class="btn btn-sm btn-primary w-100 mt-2" data-task-number="${escapedTaskNumber}" onclick="openSapDetailModal(this.dataset.taskNumber)">
                    <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">info</i> Detail SAP
                </button>
            </div>
        `;
        document.getElementById('popup-content').innerHTML = content;
        popupOverlay.setPosition(coordinate);
    }
    
    // Function to open SAP detail modal (make it globally accessible)
    window.openSapDetailModal = function(taskNumber) {
        if (!taskNumber || taskNumber === 'N/A') {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Task number tidak tersedia',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        // Find SAP data by task_number from both global sapData and filteredSidebarData
        let foundSapData = null;
        const globalSapData = typeof window.sapData !== 'undefined' ? window.sapData : sapData;
        if (globalSapData && Array.isArray(globalSapData)) {
            foundSapData = globalSapData.find(s => s.task_number === taskNumber);
        }
        if (!foundSapData && typeof filteredSidebarData !== 'undefined' && filteredSidebarData.sap) {
            foundSapData = filteredSidebarData.sap.find(s => s.task_number === taskNumber);
        }
        
        if (!foundSapData) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Data SAP tidak ditemukan',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('sapDetailModal'));
        modal.show();
        
        // Populate modal content
        populateSapDetailModal(foundSapData);
    };
    
    // Function to populate SAP detail modal
    function populateSapDetailModal(sap) {
        const modalBody = document.getElementById('sapDetailModalBody');
        const modalTitle = document.getElementById('sapDetailModalLabel');
        
        // Set title
        modalTitle.textContent = `Detail SAP - ${sap.jenis_laporan || 'SAP'} ${sap.task_number || ''}`;
        
        // Format tanggal
        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            try {
                const date = new Date(dateStr);
                // Kurangi 7 jam dari waktu database
                date.setHours(date.getHours() - 7);
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
            } catch (e) {
                return dateStr;
            }
        }
        
        // Build HTML content
        const html = `
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="card-title text-primary mb-3"><i class="material-icons-outlined me-2" style="font-size: 18px;">assignment</i> Informasi Umum</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%;">Task Number:</td>
                                    <td><strong>${sap.task_number || 'N/A'}</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Jenis Laporan:</td>
                                    <td><span class="badge bg-primary">${sap.jenis_laporan || 'N/A'}</span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Aktivitas Pekerjaan:</td>
                                    <td>${sap.aktivitas_pekerjaan || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tanggal Pelaporan:</td>
                                    <td>${formatDate(sap.tanggal_pelaporan || sap.detected_at)}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="card-title text-success mb-3"><i class="material-icons-outlined me-2" style="font-size: 18px;">location_on</i> Lokasi</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%;">Lokasi:</td>
                                    <td>${sap.lokasi || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Detail Lokasi:</td>
                                    <td>${sap.detail_lokasi || 'N/A'}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="card-title text-info mb-3"><i class="material-icons-outlined me-2" style="font-size: 18px;">description</i> Keterangan</h6>
                            <p class="mb-0">${sap.keterangan || 'Tidak ada keterangan'}</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="card-title text-warning mb-3"><i class="material-icons-outlined me-2" style="font-size: 18px;">person</i> Pelapor</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%;">Nama:</td>
                                    <td><strong>${sap.nama_pelapor || sap.pelapor || 'N/A'}</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">SID:</td>
                                    <td>${sap.sid_pelapor || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">NIK:</td>
                                    <td>${sap.nik_pelapor || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Jabatan:</td>
                                    <td>${sap.jabatan_fungsional_pelapor || sap.jabatan_fungsional_karyawan_pelapor || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Jabatan Struktural:</td>
                                    <td>${sap.jabatan_struktural_pelapor || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Departemen:</td>
                                    <td>${sap.departemen_pelapor || sap.departement_pelapor_karyawan || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Divisi:</td>
                                    <td>${sap.divisi_pelapor || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Perusahaan:</td>
                                    <td>${sap.nama_perusahaan_pelapor_karyawan || sap.perusahaan_pelapor || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Status:</td>
                                    <td><span class="badge ${sap.status_karyawan_pelapor === 'AKTIF' ? 'bg-success' : 'bg-secondary'}">${sap.status_karyawan_pelapor || 'N/A'}</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="card-title text-danger mb-3"><i class="material-icons-outlined me-2" style="font-size: 18px;">contact_mail</i> PIC</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%;">Nama:</td>
                                    <td><strong>${sap.pic || 'N/A'}</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">SID:</td>
                                    <td>${sap.sid_pic || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Jabatan:</td>
                                    <td>${sap.jabatan_fungsional_pic || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Departemen:</td>
                                    <td>${sap.departemen_pic || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Perusahaan:</td>
                                    <td>${sap.perusahaan_pic || 'N/A'}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                ${sap.url_foto ? `
                <div class="col-12 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="card-title mb-3"><i class="material-icons-outlined me-2" style="font-size: 18px;">photo</i> Foto</h6>
                            <a href="${sap.url_foto}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="material-icons-outlined me-1" style="font-size: 16px;">open_in_new</i> Lihat Foto
                            </a>
                        </div>
                    </div>
                </div>
                ` : ''}
                
                ${sap.tools_pengawasan ? `
                <div class="col-md-6 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="card-title mb-3"><i class="material-icons-outlined me-2" style="font-size: 18px;">build</i> Tools Pengawasan</h6>
                            <p class="mb-0">${sap.tools_pengawasan}</p>
                        </div>
                    </div>
                </div>
                ` : ''}
                
                ${sap.catatan_tindakan ? `
                <div class="col-md-6 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="card-title mb-3"><i class="material-icons-outlined me-2" style="font-size: 18px;">note</i> Catatan Tindakan</h6>
                            <p class="mb-0">${sap.catatan_tindakan}</p>
                        </div>
                    </div>
                </div>
                ` : ''}
            </div>
        `;
        
        modalBody.innerHTML = html;
    }
    
    // Variable to store selected area kerja for SAP filtering
    let selectedAreaKerjaForSap = null;
    let currentAreaKerjaLokasi = null; // Store lokasi name for filtering
    
    // Function to filter SAP by area CCTV
    window.filterSapByAreaCctv = function(cctvNo, cctvName, cctvLokasi) {
        // Close popup
        popupOverlay.setPosition(undefined);
        
        // Store lokasi for filtering
        currentAreaKerjaLokasi = cctvLokasi || cctvName;
        
        // Show modal untuk pilih week
        const modalHtml = `
            <div class="p-3">
                <h6 class="mb-3">Filter SAP di Area CCTV: <strong>${cctvName}</strong></h6>
                <p class="text-muted small mb-3">Lokasi: ${cctvLokasi || 'N/A'}</p>
                <div class="mb-3">
                    <label class="form-label">Pilih Week:</label>
                    <input type="week" id="areaCctvSapWeekFilter" class="form-control">
                </div>
                <div class="mb-3">
                    <small class="text-muted" id="areaCctvSapWeekRange">Week: -</small>
                </div>
                <button class="btn btn-primary w-100" onclick="applyAreaCctvSapFilter()">
                    <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">search</i> Filter SAP
                </button>
            </div>
        `;
        
        Swal.fire({
            title: 'Filter SAP per Area CCTV',
            html: modalHtml,
            showCancelButton: true,
            confirmButtonText: 'Filter',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#3085d6',
            didOpen: () => {
                // Set default week (minggu ini)
                const today = new Date();
                const monday = new Date(today);
                monday.setDate(today.getDate() - (today.getDay() === 0 ? 6 : today.getDay() - 1));
                const year = monday.getFullYear();
                const week = getWeekNumber(monday);
                const weekValue = `${year}-W${String(week).padStart(2, '0')}`;
                
                const weekFilter = document.getElementById('areaCctvSapWeekFilter');
                if (weekFilter) {
                    weekFilter.value = weekValue;
                    updateAreaCctvSapWeekRange();
                    
                    weekFilter.addEventListener('change', function() {
                        updateAreaCctvSapWeekRange();
                    });
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                applyAreaCctvSapFilter();
            }
        });
    }
    
    // Function to update week range display for area CCTV SAP filter
    function updateAreaCctvSapWeekRange() {
        const weekFilter = document.getElementById('areaCctvSapWeekFilter');
        const weekRange = document.getElementById('areaCctvSapWeekRange');
        if (!weekFilter || !weekRange) return;
        
        const weekValue = weekFilter.value;
        if (!weekValue) {
            weekRange.textContent = 'Week: -';
            return;
        }
        
        const [year, week] = weekValue.split('-W');
        const weekStart = getDateOfISOWeek(parseInt(week), parseInt(year));
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekStart.getDate() + 6);
        
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const startStr = `${weekStart.getDate()} ${months[weekStart.getMonth()]} ${weekStart.getFullYear()}`;
        const endStr = `${weekEnd.getDate()} ${months[weekEnd.getMonth()]} ${weekEnd.getFullYear()}`;
        
        weekRange.textContent = `Week: ${startStr} - ${endStr}`;
    }
    
    // Function to apply SAP filter by area CCTV
    window.applyAreaCctvSapFilter = function() {
        if (!currentAreaKerjaLokasi) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Lokasi area CCTV tidak ditemukan',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        const weekFilter = document.getElementById('areaCctvSapWeekFilter');
        if (!weekFilter || !weekFilter.value) {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Silakan pilih week terlebih dahulu',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        const weekValue = weekFilter.value;
        const [year, week] = weekValue.split('-W');
        const weekStart = getDateOfISOWeek(parseInt(week), parseInt(year));
        weekStart.setHours(0, 0, 0, 0);
        
        const yearStr = weekStart.getFullYear();
        const monthStr = String(weekStart.getMonth() + 1).padStart(2, '0');
        const dayStr = String(weekStart.getDate()).padStart(2, '0');
        const weekStartStr = `${yearStr}-${monthStr}-${dayStr} 00:00:00`;
        
        // Show loading
        Swal.fire({
            title: 'Memuat SAP',
            html: `Memfilter SAP di area CCTV <strong>${currentAreaKerjaLokasi}</strong> untuk week yang dipilih...`,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Load SAP data for the week
        fetch(`{{ route('maps.api.filtered-data') }}?week_start=${encodeURIComponent(weekStartStr)}&show_sap=true&show_hazard=true`)
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success && data.data && (data.data.sap || data.data.hazard)) {
                    const allSapData = data.data.sap || data.data.hazard || [];
                    
                    // Filter SAP berdasarkan lokasi atau detail_lokasi yang cocok dengan area CCTV
                    const filteredSap = allSapData.filter(sap => {
                        const sapLokasi = (sap.lokasi || '').toLowerCase().trim();
                        const sapDetailLokasi = (sap.detail_lokasi || '').toLowerCase().trim();
                        const areaCctvLokasi = currentAreaKerjaLokasi.toLowerCase().trim();
                        
                        // Check if SAP lokasi or detail_lokasi contains area CCTV lokasi or vice versa
                        const lokasiMatch = sapLokasi.includes(areaCctvLokasi) || areaCctvLokasi.includes(sapLokasi);
                        const detailLokasiMatch = sapDetailLokasi.includes(areaCctvLokasi) || areaCctvLokasi.includes(sapDetailLokasi);
                        const exactMatch = sapLokasi === areaCctvLokasi || sapDetailLokasi === areaCctvLokasi;
                        
                        return lokasiMatch || detailLokasiMatch || exactMatch;
                    });
                    
                    console.log(`Found ${filteredSap.length} SAP items in area CCTV out of ${allSapData.length} total`);
                    
                    // Simpan semua data hasil filter untuk count di tab
                    sapDataAllWeek = [...filteredSap];
                    
                    // Urutkan semua data berdasarkan tanggal terbaru
                    const sortedSapAll = [...filteredSap].sort((a, b) => {
                        const dateA = new Date(a.tanggal_pelaporan || a.detected_at || 0);
                        const dateB = new Date(b.tanggal_pelaporan || b.detected_at || 0);
                        return dateB - dateA; // Terbaru di atas
                    });
                    
                    // Filter data hari ini untuk sidebar
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    const todayStr = today.toISOString().split('T')[0];
                    
                    const sapDataToday = sortedSapAll.filter(sap => {
                        if (!sap.tanggal_pelaporan && !sap.detected_at) return false;
                        try {
                            const sapDate = new Date(sap.tanggal_pelaporan || sap.detected_at);
                            sapDate.setHours(0, 0, 0, 0);
                            const sapDateStr = sapDate.toISOString().split('T')[0];
                            return sapDateStr === todayStr;
                        } catch (e) {
                            return false;
                        }
                    });
                    
                    // Map: Hanya tampilkan 1000 data terbaru dari semua data hasil filter
                    const sapDataForMap = sortedSapAll.slice(0, 1000);
                    
                    // Update sidebar dengan data hari ini saja
                    filteredSidebarData.sap = sapDataToday;
                    sapDataForSidebar = sapDataToday;
                    sapData = sapDataForMap; // Untuk map, hanya 1000 terbaru
                    
                    // Update tab counts (menggunakan semua data hasil filter untuk count)
                    updateTabCounts();
                    
                    // Switch to SAP tab
                    currentSidebarTab = 'sap';
                    document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
                    const sapTab = document.querySelector('[data-tab="sap"]');
                    if (sapTab) {
                        sapTab.classList.add('active');
                    }
                    
                    // Render SAP list (tampilkan hanya data hari ini)
                    renderSidebarTab('sap');
                    
                    // Update map markers (hanya 1000 terbaru)
                    updateSapMarkersOnMap(sapDataForMap);
                    
                    // Update evaluation alerts if enabled
                    if (evaluationEnabled) {
                        showEvaluationAlerts();
                    }
                    
                    console.log(`Total filtered: ${sapDataAllWeek.length} SAP items | Sidebar (today): ${sapDataToday.length} SAP items | Map: ${sapDataForMap.length} SAP markers (limited to 1000)`);
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: `Ditemukan ${filteredSap.length} SAP di area CCTV untuk week yang dipilih`,
                        confirmButtonColor: '#3085d6',
                        timer: 2000
                    });
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'Tidak Ada Data',
                        text: 'Tidak ada data SAP untuk week yang dipilih',
                        confirmButtonColor: '#3085d6'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error loading SAP data:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Gagal memuat data SAP',
                    confirmButtonColor: '#3085d6'
                });
            });
    }
    
    // Function to filter SAP by area kerja
    window.filterSapByAreaKerja = function(areaKerjaId, lokasiName) {
        // Close popup
        popupOverlay.setPosition(undefined);
        
        // Find area kerja feature
        if (!areaKerjaBmo2PamaLayer) {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Layer Area Kerja tidak ditemukan',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        const areaKerjaFeatures = areaKerjaBmo2PamaLayer.getSource().getFeatures();
        const areaKerjaFeature = areaKerjaFeatures.find(f => {
            const props = f.getProperties();
            return props.id_lokasi === areaKerjaId || props.lokasi === lokasiName;
        });
        
        if (!areaKerjaFeature) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Area Kerja tidak ditemukan',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        // Store selected area kerja
        selectedAreaKerjaForSap = {
            id: areaKerjaId,
            name: lokasiName,
            feature: areaKerjaFeature
        };
        
        // Store lokasi name for filtering
        currentAreaKerjaLokasi = lokasiName;
        
        // Show modal untuk pilih week
        showAreaKerjaSapFilterModal(areaKerjaFeature, lokasiName);
    }
    
    // Function to show modal for selecting week filter for area kerja SAP
    function showAreaKerjaSapFilterModal(areaKerjaFeature, lokasiName) {
        // Get geometry from feature
        const geometry = areaKerjaFeature.getGeometry();
        if (!geometry) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Geometry area kerja tidak ditemukan',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        // Get polygon coordinates
        const coordinates = geometry.getCoordinates();
        let polygonCoords = [];
        
        // Handle MultiPolygon or Polygon
        if (coordinates[0][0] && Array.isArray(coordinates[0][0][0])) {
            // MultiPolygon - use first polygon
            polygonCoords = coordinates[0][0];
        } else if (coordinates[0] && Array.isArray(coordinates[0][0])) {
            // Polygon
            polygonCoords = coordinates[0];
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Format koordinat area kerja tidak valid',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        // Convert to EPSG:4326 if needed
        const polygonCoords4326 = polygonCoords.map(coord => {
            return ol.proj.toLonLat(coord, map.getView().getProjection());
        });
        
        // Store polygon coordinates globally
        currentAreaKerjaPolygonCoords = polygonCoords4326;
        
        // Show modal with week selector
        const modalHtml = `
            <div class="p-3">
                <h6 class="mb-3">Filter SAP di Area Kerja: <strong>${lokasiName}</strong></h6>
                <div class="mb-3">
                    <label class="form-label">Pilih Week:</label>
                    <input type="week" id="areaKerjaSapWeekFilter" class="form-control">
                </div>
                <div class="mb-3">
                    <small class="text-muted" id="areaKerjaSapWeekRange">Week: -</small>
                </div>
                <button class="btn btn-primary w-100" onclick="applyAreaKerjaSapFilter()">
                    <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">search</i> Filter SAP
                </button>
            </div>
        `;
        
        Swal.fire({
            title: 'Filter SAP per Area Kerja',
            html: modalHtml,
            showCancelButton: true,
            confirmButtonText: 'Filter',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#3085d6',
            didOpen: () => {
                // Set default week (minggu ini)
                const today = new Date();
                const monday = new Date(today);
                monday.setDate(today.getDate() - (today.getDay() === 0 ? 6 : today.getDay() - 1));
                const year = monday.getFullYear();
                const week = getWeekNumber(monday);
                const weekValue = `${year}-W${String(week).padStart(2, '0')}`;
                
                const weekFilter = document.getElementById('areaKerjaSapWeekFilter');
                if (weekFilter) {
                    weekFilter.value = weekValue;
                    updateAreaKerjaSapWeekRange();
                    
                    weekFilter.addEventListener('change', function() {
                        updateAreaKerjaSapWeekRange();
                    });
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                applyAreaKerjaSapFilter();
            }
        });
    }
    
    // Function to update week range display for area kerja SAP filter
    function updateAreaKerjaSapWeekRange() {
        const weekFilter = document.getElementById('areaKerjaSapWeekFilter');
        const weekRange = document.getElementById('areaKerjaSapWeekRange');
        if (!weekFilter || !weekRange) return;
        
        const weekValue = weekFilter.value;
        if (!weekValue) {
            weekRange.textContent = 'Week: -';
            return;
        }
        
        const [year, week] = weekValue.split('-W');
        const weekStart = getDateOfISOWeek(parseInt(week), parseInt(year));
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekStart.getDate() + 6);
        
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const startStr = `${weekStart.getDate()} ${months[weekStart.getMonth()]} ${weekStart.getFullYear()}`;
        const endStr = `${weekEnd.getDate()} ${months[weekEnd.getMonth()]} ${weekEnd.getFullYear()}`;
        
        weekRange.textContent = `Week: ${startStr} - ${endStr}`;
    }
    
    // Function to apply SAP filter by area kerja
    window.applyAreaKerjaSapFilter = function() {
        if (!currentAreaKerjaLokasi) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Lokasi area kerja tidak ditemukan',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        const weekFilter = document.getElementById('areaKerjaSapWeekFilter');
        if (!weekFilter || !weekFilter.value) {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Silakan pilih week terlebih dahulu',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        const weekValue = weekFilter.value;
        const [year, week] = weekValue.split('-W');
        const weekStart = getDateOfISOWeek(parseInt(week), parseInt(year));
        weekStart.setHours(0, 0, 0, 0);
        
        const yearStr = weekStart.getFullYear();
        const monthStr = String(weekStart.getMonth() + 1).padStart(2, '0');
        const dayStr = String(weekStart.getDate()).padStart(2, '0');
        const weekStartStr = `${yearStr}-${monthStr}-${dayStr} 00:00:00`;
        
        // Show loading
        Swal.fire({
            title: 'Memuat SAP',
            html: `Memfilter SAP di area kerja <strong>${currentAreaKerjaLokasi}</strong> untuk week yang dipilih...`,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Load SAP data for the week
        fetch(`{{ route('maps.api.filtered-data') }}?week_start=${encodeURIComponent(weekStartStr)}&show_sap=true&show_hazard=true`)
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success && data.data && (data.data.sap || data.data.hazard)) {
                    const allSapData = data.data.sap || data.data.hazard || [];
                    
                    // Filter SAP berdasarkan lokasi atau detail_lokasi yang cocok dengan area kerja
                    const filteredSap = allSapData.filter(sap => {
                        const sapLokasi = (sap.lokasi || '').toLowerCase().trim();
                        const sapDetailLokasi = (sap.detail_lokasi || '').toLowerCase().trim();
                        const areaKerjaLokasi = currentAreaKerjaLokasi.toLowerCase().trim();
                        
                        // Check if SAP lokasi or detail_lokasi contains area kerja lokasi or vice versa
                        // Support partial matching untuk fleksibilitas
                        const lokasiMatch = sapLokasi.includes(areaKerjaLokasi) || areaKerjaLokasi.includes(sapLokasi);
                        const detailLokasiMatch = sapDetailLokasi.includes(areaKerjaLokasi) || areaKerjaLokasi.includes(sapDetailLokasi);
                        
                        // Also check exact match
                        const exactMatch = sapLokasi === areaKerjaLokasi || sapDetailLokasi === areaKerjaLokasi;
                        
                        return lokasiMatch || detailLokasiMatch || exactMatch;
                    });
                    
                    console.log(`Found ${filteredSap.length} SAP items in area kerja out of ${allSapData.length} total`);
                    
                    // Simpan semua data hasil filter untuk count di tab
                    sapDataAllWeek = [...filteredSap];
                    
                    // Urutkan semua data berdasarkan tanggal terbaru
                    const sortedSapAll = [...filteredSap].sort((a, b) => {
                        const dateA = new Date(a.tanggal_pelaporan || a.detected_at || 0);
                        const dateB = new Date(b.tanggal_pelaporan || b.detected_at || 0);
                        return dateB - dateA; // Terbaru di atas
                    });
                    
                    // Filter data hari ini untuk sidebar
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    const todayStr = today.toISOString().split('T')[0];
                    
                    const sapDataToday = sortedSapAll.filter(sap => {
                        if (!sap.tanggal_pelaporan && !sap.detected_at) return false;
                        try {
                            const sapDate = new Date(sap.tanggal_pelaporan || sap.detected_at);
                            sapDate.setHours(0, 0, 0, 0);
                            const sapDateStr = sapDate.toISOString().split('T')[0];
                            return sapDateStr === todayStr;
                        } catch (e) {
                            return false;
                        }
                    });
                    
                    // Map: Hanya tampilkan 1000 data terbaru dari semua data hasil filter
                    const sapDataForMap = sortedSapAll.slice(0, 1000);
                    
                    // Update sidebar dengan data hari ini saja
                    filteredSidebarData.sap = sapDataToday;
                    sapDataForSidebar = sapDataToday;
                    sapData = sapDataForMap; // Untuk map, hanya 1000 terbaru
                    
                    // Update tab counts (menggunakan semua data hasil filter untuk count)
                    updateTabCounts();
                    
                    // Switch to SAP tab
                    currentSidebarTab = 'sap';
                    document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
                    const sapTab = document.querySelector('[data-tab="sap"]');
                    if (sapTab) {
                        sapTab.classList.add('active');
                    }
                    
                    // Render SAP list (tampilkan hanya data hari ini)
                    renderSidebarTab('sap');
                    
                    // Update map markers (hanya 1000 terbaru)
                    updateSapMarkersOnMap(sapDataForMap);
                    
                    // Update evaluation alerts if enabled
                    if (evaluationEnabled) {
                        showEvaluationAlerts();
                    }
                    
                    console.log(`Total filtered: ${sapDataAllWeek.length} SAP items | Sidebar (today): ${sapDataToday.length} SAP items | Map: ${sapDataForMap.length} SAP markers (limited to 1000)`);
                    
                    // Highlight area kerja
                    if (selectedAreaKerjaForSap && selectedAreaKerjaForSap.feature) {
                        highlightAreaKerjaForSap(selectedAreaKerjaForSap.feature);
                    }
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: `Ditemukan ${filteredSap.length} SAP di area kerja untuk week yang dipilih`,
                        confirmButtonColor: '#3085d6',
                        timer: 2000
                    });
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'Tidak Ada Data',
                        text: 'Tidak ada data SAP untuk week yang dipilih',
                        confirmButtonColor: '#3085d6'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error loading SAP data:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Gagal memuat data SAP',
                    confirmButtonColor: '#3085d6'
                });
            });
    }
    
    // Function to highlight area kerja for SAP
    function highlightAreaKerjaForSap(areaKerjaFeature) {
        // Remove existing highlight
        if (highlightedAreaKerjaLayer) {
            map.removeLayer(highlightedAreaKerjaLayer);
            highlightedAreaKerjaLayer = null;
        }
        
        // Create highlight layer
        const highlightSource = new ol.source.Vector({
            features: [areaKerjaFeature.clone()]
        });
        
        highlightedAreaKerjaLayer = new ol.layer.Vector({
            source: highlightSource,
            style: function(feature) {
                const props = feature.getProperties();
                const areaKerja = props.area_kerja || '';
                
                let fillColor = 'rgba(59, 130, 246, 0.4)';
                let strokeColor = '#3b82f6';
                let strokeWidth = 3;
                
                if (areaKerja === 'Pit') {
                    fillColor = 'rgba(239, 68, 68, 0.4)';
                    strokeColor = '#ef4444';
                } else if (areaKerja === 'Hauling') {
                    fillColor = 'rgba(245, 158, 11, 0.4)';
                    strokeColor = '#f59e0b';
                } else if (areaKerja === 'Infra Tambang') {
                    fillColor = 'rgba(59, 130, 246, 0.4)';
                    strokeColor = '#3b82f6';
                }
                
                return new ol.style.Style({
                    fill: new ol.style.Fill({ color: fillColor }),
                    stroke: new ol.style.Stroke({
                        color: strokeColor,
                        width: strokeWidth
                    })
                });
            },
            zIndex: 2000
        });
        
        map.addLayer(highlightedAreaKerjaLayer);
        
        // Fit map to show area kerja
        const extent = highlightedAreaKerjaLayer.getSource().getExtent();
        map.getView().fit(extent, {
            padding: [50, 50, 50, 50],
            duration: 500
        });
    }

    function showInsidenPopup(coordinate, insiden) {
        if (!insiden) {
            return;
        }

        const escapedNo = insiden.no_kecelakaan ? insiden.no_kecelakaan.replace(/"/g, '&quot;') : '';
        const content = `
            <div style="min-width: 220px;">
                <h6 style="margin: 0 0 8px 0;">${insiden.no_kecelakaan}</h6>
                <p style="margin: 5px 0; font-size: 13px;">
                    <strong>Site:</strong> ${insiden.site || 'N/A'}<br>
                    <strong>Layer:</strong> ${insiden.layer || 'N/A'}<br>
                    <strong>Kategori:</strong> ${insiden.kategori || 'N/A'}<br>
                    <strong>Status LPI:</strong> ${insiden.status_lpi || 'N/A'}
                </p>
                <button class="btn btn-sm btn-primary w-100" data-no-kec="${escapedNo}" onclick="openInsidenModal(this.dataset.noKec)">
                    Detail Insiden
                </button>
            </div>
        `;

        document.getElementById('popup-content').innerHTML = content;
        popupOverlay.setPosition(coordinate);
    }

    // Function to populate site filter dropdown - ambil dari database
    function populateSiteFilter() {
        if (!siteFilter) {
            return;
        }

        // Ambil data site dari database melalui API
        fetch('{{ route("hazard-detection.api.sites-list") }}')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    // Populate dropdown dengan data dari database
                    siteFilter.innerHTML = '<option value="">Semua Site</option>';
                    data.data.forEach(function(site) {
                        if (site && site.trim()) {
                            const option = document.createElement('option');
                            option.value = site.trim();
                            option.textContent = site.trim();
                            siteFilter.appendChild(option);
                        }
                    });
                } else {
                    // Fallback: ambil dari data lokal jika API gagal
                    const sites = new Set();
                    
                    // From CCTV locations (fallback)
                    cctvLocations.forEach(function(cctv) {
                        if (cctv.site) {
                            sites.add(cctv.site);
                        }
                    });
                    
                    // From hazard detections
                    hazardDetections.forEach(function(hazard) {
                        if (hazard.site || hazard.nama_site) {
                            sites.add(hazard.site || hazard.nama_site);
                        }
                    });
                    
                    // From insiden dataset
                    insidenDataset.forEach(function(insiden) {
                        if (insiden.site) {
                            sites.add(insiden.site);
                        }
                    });
                    
                    // Sort sites alphabetically
                    const sortedSites = Array.from(sites).sort();
                    
                    // Populate dropdown
                    siteFilter.innerHTML = '<option value="">Semua Site</option>';
                    sortedSites.forEach(function(site) {
                        const option = document.createElement('option');
                        option.value = site;
                        option.textContent = site;
                        siteFilter.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading sites from database:', error);
                // Fallback: ambil dari data lokal jika API error
                const sites = new Set();
                
                cctvLocations.forEach(function(cctv) {
                    if (cctv.site) {
                        sites.add(cctv.site);
                    }
                });
                
                hazardDetections.forEach(function(hazard) {
                    if (hazard.site || hazard.nama_site) {
                        sites.add(hazard.site || hazard.nama_site);
                    }
                });
                
                insidenDataset.forEach(function(insiden) {
                    if (insiden.site) {
                        sites.add(insiden.site);
                    }
                });
                
                const sortedSites = Array.from(sites).sort();
                siteFilter.innerHTML = '<option value="">Semua Site</option>';
                sortedSites.forEach(function(site) {
                    const option = document.createElement('option');
                    option.value = site;
                    option.textContent = site;
                    siteFilter.appendChild(option);
                });
            });
    }

    // Function to update statistics based on site filter
    function updateStatisticsBySite(site) {
        // Filter data based on site
        let filteredHazards = hazardDetections;
        let filteredCctv = cctvLocations;
        let filteredInsiden = insidenDataset;
        
        if (site) {
            // Normalize site name for comparison (trim, uppercase)
            const normalizedSite = site.trim().toUpperCase();
            
            filteredHazards = hazardDetections.filter(function(h) {
                const hazardSite = (h.site || h.nama_site || '').toString().trim().toUpperCase();
                return hazardSite === normalizedSite;
            });
            
            filteredCctv = cctvLocations.filter(function(c) {
                const cctvSite = (c.site || '').toString().trim().toUpperCase();
                return cctvSite === normalizedSite;
            });
            
            filteredInsiden = insidenDataset.filter(function(i) {
                const insidenSite = (i.site || '').toString().trim();
                if (!insidenSite) return false;
                
                // Normalize both site names: remove spaces, dashes, and convert to uppercase
                const normalizeSiteName = function(siteName) {
                    if (!siteName) return '';
                    return siteName.toString().trim()
                        .toUpperCase()
                        .replace(/\s+/g, '')  // Remove all spaces
                        .replace(/[^A-Z0-9]/g, ''); // Remove special characters except letters and numbers
                };
                
                const normalizedInsidenSite = normalizeSiteName(insidenSite);
                const normalizedFilterSite = normalizeSiteName(normalizedSite);
                
                // Debug logging (can be removed in production)
                // console.log('Filtering insiden:', {
                //     originalSite: insidenSite,
                //     normalizedInsidenSite: normalizedInsidenSite,
                //     filterSite: normalizedSite,
                //     normalizedFilterSite: normalizedFilterSite,
                //     match: normalizedInsidenSite === normalizedFilterSite || 
                //            normalizedInsidenSite.includes(normalizedFilterSite) || 
                //            normalizedFilterSite.includes(normalizedInsidenSite)
                // });
                
                // Check if normalized sites match exactly
                if (normalizedInsidenSite === normalizedFilterSite) {
                    return true;
                }
                
                // Check if one contains the other (for partial matches)
                // This handles cases like "BMO2" matching "BMO2PAMA" or "BMO2" matching "BMO2"
                if (normalizedInsidenSite.includes(normalizedFilterSite) || 
                    normalizedFilterSite.includes(normalizedInsidenSite)) {
                    return true;
                }
                
                // Additional check: extract base name and number for better matching
                // This handles "BMO2" matching "BMO 2", "BMO-2", "BMO2 PAMA", etc.
                const extractBaseAndNumber = function(site) {
                    // Match pattern like "BMO2", "BMO 2", "BMO-2", etc.
                    const match = site.match(/^([A-Z]+)(\d+)/);
                    if (match) {
                        return { base: match[1], number: match[2] };
                    }
                    return null;
                };
                
                const insidenParts = extractBaseAndNumber(normalizedInsidenSite);
                const filterParts = extractBaseAndNumber(normalizedFilterSite);
                
                if (insidenParts && filterParts) {
                    // Match if base name and number are the same
                    // e.g., "BMO" + "2" matches "BMO" + "2"
                    if (insidenParts.base === filterParts.base && 
                        insidenParts.number === filterParts.number) {
                        return true;
                    }
                }
                
                return false;
            });
            
            // Debug: log filtered results
            // console.log('Filtered insiden for site "' + site + '":', filteredInsiden.length, 'of', insidenDataset.length);
        }
        
        // Calculate HAZARD statistics
        const hazardCount = filteredHazards.length;
        const activeHazards = filteredHazards.filter(function(h) {
            return h.status === 'active';
        }).length;
        const resolvedHazards = filteredHazards.filter(function(h) {
            return h.status === 'resolved';
        }).length;
        
        // Calculate INSIDEN statistics
        const insidenCount = filteredInsiden.length;
        
        // Calculate GR (Golden Rules) statistics - count hazards with golden rule
        const grCount = filteredHazards.filter(function(h) {
            return h.nama_goldenrule && h.nama_goldenrule !== 'N/A' && h.nama_goldenrule !== '';
        }).length;
        
        // Calculate totals for percentage calculation
        const totalHazards = hazardDetections.length;
        const totalInsiden = insidenDataset.length;
        const totalGr = hazardDetections.filter(function(h) {
            return h.nama_goldenrule && h.nama_goldenrule !== 'N/A' && h.nama_goldenrule !== '';
        }).length;
        
        // Calculate percentages for donut charts
        // Percentage shows how much of the filtered data represents from total data
        const hazardPercentage = totalHazards > 0 ? Math.round((hazardCount / totalHazards) * 100) : 100;
        const insidenPercentage = totalInsiden > 0 ? Math.round((insidenCount / totalInsiden) * 100) : 100;
        const grPercentage = totalGr > 0 ? Math.round((grCount / totalGr) * 100) : 100;
        
        // Update HAZARD display with animation
        const statHazardCount = document.getElementById('statHazardCount');
        const statHazardChange = document.getElementById('statHazardChange');
        const statHazardText = document.getElementById('statHazardText');
        if (statHazardCount) {
            animateNumber('statHazardCount', hazardCount, 800);
        }
        if (statHazardChange) {
            // Calculate percentage of total (or use a default calculation)
            // For now, using percentage of active vs total filtered
            const percentage = hazardCount > 0 ? ((activeHazards / hazardCount) * 100).toFixed(1) : '0.0';
            statHazardChange.textContent = percentage + '%';
        }
        if (statHazardText) {
            statHazardText.textContent = hazardCount + ' hazards';
        }
        
        // TBC (To Be Concerned) - tidak diupdate dengan data CCTV
        // TBC adalah data statis dari database hazard_validations, tetap menggunakan data awal dari PHP
        // Tidak perlu mengupdate TBC karena data sudah benar dari server-side
        
        // Update INSIDEN display with animation
        const statInsidenCount = document.getElementById('statInsidenCount');
        const statInsidenChange = document.getElementById('statInsidenChange');
        const statInsidenText = document.getElementById('statInsidenText');
        if (statInsidenCount) {
            animateNumber('statInsidenCount', insidenCount, 800);
        }
        if (statInsidenChange) {
            // Percentage of total insiden
            const percentage = totalInsiden > 0 ? ((insidenCount / totalInsiden) * 100).toFixed(1) : '0.0';
            statInsidenChange.textContent = percentage + '%';
        }
        if (statInsidenText) {
            statInsidenText.textContent = insidenCount + ' insiden';
        }
        
        // Update GR display with animation
        const statGrCount = document.getElementById('statGrCount');
        const statGrChange = document.getElementById('statGrChange');
        const statGrText = document.getElementById('statGrText');
        if (statGrCount) {
            animateNumber('statGrCount', grCount, 800);
        }
        if (statGrChange) {
            // Percentage of total GR
            const percentage = totalGr > 0 ? ((grCount / totalGr) * 100).toFixed(1) : '0.0';
            statGrChange.textContent = percentage + '%';
        }
        if (statGrText) {
            statGrText.textContent = grCount + ' golden rules';
        }
        
        // Update donut charts
        updateDonutChart('donutHazard', hazardPercentage, '#0d6efd');
        // Donut chart CCTV akan diupdate di dalam fetch API untuk konsistensi dengan data database
        updateDonutChart('donutInsiden', insidenPercentage, '#fd7e14');
        updateDonutChart('donutGr', grPercentage, '#20c997');
    }

    // Function to update donut chart
    // Store current percentage for each donut chart for animation
    const donutChartState = {
        donutHazard: 0,
        donutCctv: 0,
        donutInsiden: 0,
        donutGr: 0
    };
    
    // Store animation frame IDs to cancel if needed
    const donutAnimationFrames = {};
    
    // Store current values for number animation
    const numberAnimationState = {
        statHazardCount: 0,
        statCctvCount: 0,
        statInsidenCount: 0,
        statGrCount: 0
    };
    
    // Store animation frame IDs for number animations
    const numberAnimationFrames = {};
    
    // Function to animate number with smooth transition
    // Updated to support multiple elements with the same ID
    function animateNumber(elementId, targetValue, duration = 800) {
        // Get all elements with this ID (querySelectorAll for multiple elements)
        const elements = document.querySelectorAll(`#${elementId}`);
        if (!elements || elements.length === 0) return;
        
        // Get current value from state or parse from first element
        let currentValue = numberAnimationState[elementId];
        if (currentValue === undefined || currentValue === null) {
            const currentText = elements[0].textContent || '0';
            // Remove formatting (commas, spaces) and parse
            currentValue = parseInt(currentText.replace(/[^\d]/g, '')) || 0;
        }
        
        // Cancel any existing animation for this element
        if (numberAnimationFrames[elementId]) {
            cancelAnimationFrame(numberAnimationFrames[elementId]);
        }
        
        // If values are the same, no need to animate
        if (Math.abs(currentValue - targetValue) < 1) {
            numberAnimationState[elementId] = targetValue;
            const formattedValue = targetValue.toLocaleString('id-ID');
            elements.forEach(el => {
                el.textContent = formattedValue;
            });
            return;
        }
        
        // Animation parameters
        const startTime = performance.now();
        const startValue = currentValue;
        const endValue = targetValue;
        
        // Easing function for smooth animation (ease-out cubic)
        function easeOutCubic(t) {
            return 1 - Math.pow(1 - t, 3);
        }
        
        // Animation function
        function animate(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Apply easing
            const easedProgress = easeOutCubic(progress);
            
            // Calculate current value
            const currentValue = Math.round(startValue + (endValue - startValue) * easedProgress);
            
            // Update all elements with formatted number
            const formattedValue = currentValue.toLocaleString('id-ID');
            elements.forEach(el => {
                el.textContent = formattedValue;
            });
            
            // Continue animation if not finished
            if (progress < 1) {
                numberAnimationFrames[elementId] = requestAnimationFrame(animate);
            } else {
                // Animation complete, update state
                numberAnimationState[elementId] = endValue;
                const finalFormattedValue = endValue.toLocaleString('id-ID');
                elements.forEach(el => {
                    el.textContent = finalFormattedValue;
                });
                delete numberAnimationFrames[elementId];
            }
        }
        
        // Start animation
        numberAnimationFrames[elementId] = requestAnimationFrame(animate);
    }
    
    function updateDonutChart(elementId, targetPercentage, color) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        // Ensure percentage is between 0 and 100
        targetPercentage = Math.max(0, Math.min(100, targetPercentage));
        
        // Get current percentage from state
        const currentPercentage = donutChartState[elementId] || 0;
        
        // Cancel any existing animation for this chart
        if (donutAnimationFrames[elementId]) {
            cancelAnimationFrame(donutAnimationFrames[elementId]);
        }
        
        // If values are the same, no need to animate
        if (Math.abs(currentPercentage - targetPercentage) < 0.1) {
            donutChartState[elementId] = targetPercentage;
            // Still update the chart to ensure it's rendered
            if (typeof $ !== 'undefined' && typeof $.fn.peity !== 'undefined') {
                element.textContent = Math.round(targetPercentage) + '/' + 100;
                if (element._peity) {
                    try {
                        $(element).peity('destroy');
                    } catch(e) {}
                }
                try {
                    $(element).peity('donut', {
                        fill: [color, "rgb(0 0 0 / 10%)"],
                        innerRadius: 32,
                        radius: 40
                    });
                } catch(e) {
                    console.error('Error updating donut chart:', e);
                }
            }
            return;
        }
        
        // Wait for jQuery and peity to be available
        if (typeof $ === 'undefined' || typeof $.fn.peity === 'undefined') {
            setTimeout(function() {
                updateDonutChart(elementId, targetPercentage, color);
            }, 100);
            return;
        }
        
        // Animation parameters
        const duration = 800; // milliseconds
        const startTime = performance.now();
        const startValue = currentPercentage;
        const endValue = targetPercentage;
        
        // Easing function for smooth animation (ease-out cubic)
        function easeOutCubic(t) {
            return 1 - Math.pow(1 - t, 3);
        }
        
        // Animation function
        function animate(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Apply easing
            const easedProgress = easeOutCubic(progress);
            
            // Calculate current value
            const currentValue = startValue + (endValue - startValue) * easedProgress;
            
            // Update the text content for peity
            element.textContent = Math.round(currentValue) + '/' + 100;
            
            // Destroy existing peity chart if exists
            if (element._peity) {
                try {
                    $(element).peity('destroy');
                } catch(e) {
                    // Ignore destroy errors
                }
            }
            
            // Recreate peity chart with current animated value
            try {
                $(element).peity('donut', {
                    fill: [color, "rgb(0 0 0 / 10%)"],
                    innerRadius: 32,
                    radius: 40
                });
            } catch(e) {
                console.error('Error updating donut chart:', e);
            }
            
            // Continue animation if not finished
            if (progress < 1) {
                donutAnimationFrames[elementId] = requestAnimationFrame(animate);
            } else {
                // Animation complete, update state
                donutChartState[elementId] = endValue;
                delete donutAnimationFrames[elementId];
            }
        }
        
        // Start animation
        donutAnimationFrames[elementId] = requestAnimationFrame(animate);
    }
    
    // Make function globally accessible
    window.updateDonutChart = updateDonutChart;

    // Function to filter map features by site
    function filterBySite(site) {
        currentSiteFilter = site || '';
        
        // Trigger style refresh for all layers
        // OpenLayers akan otomatis memanggil style function lagi
        if (hazardLayer) {
            hazardLayer.changed();
        }
        if (cctvLayer) {
            cctvLayer.changed();
        }
        if (insidenLayer) {
            insidenLayer.changed();
        }
        
        // Filter hazard list view
        filterHazardListView(site);
        
        // Update statistics based on site filter
        updateStatisticsBySite(site);
    }

    // Function to filter hazard list view by site
    function filterHazardListView(site) {
        const hazardItems = document.querySelectorAll('.hazard-item');
        hazardItems.forEach(function(item) {
            const hazardId = item.getAttribute('data-hazard-id');
            const hazard = hazardDetections.find(h => h.id === hazardId);
            
            if (!hazard) {
                item.style.display = site ? 'none' : 'block';
                return;
            }
            
            const hazardSite = hazard.site || hazard.nama_site || null;
            if (site) {
                item.style.display = hazardSite === site ? 'block' : 'none';
            } else {
                item.style.display = 'block';
            }
        });
        
        // Filter insiden list view
        const insidenItems = document.querySelectorAll('[data-no-kecelakaan]');
        insidenItems.forEach(function(item) {
            const noKecelakaan = item.getAttribute('data-no-kecelakaan');
            const insiden = insidenDataset.find(i => i.no_kecelakaan === noKecelakaan);
            
            if (!insiden) {
                item.style.display = site ? 'none' : 'block';
                return;
            }
            
            const insidenSite = insiden.site || null;
            if (site) {
                item.style.display = insidenSite === site ? 'block' : 'none';
            } else {
                item.style.display = 'block';
            }
        });
    }

    // Event listener for site filter sudah dipindahkan ke mainFilterSiteDropdown
    // Filter site sekarang terintegrasi dengan dropdown button di card Hazard Location Map

    // Initialize site filter on page load
    setTimeout(function() {
        // populateSiteFilter() sudah tidak diperlukan karena menggunakan loadMainFilterOptions()
        
        // Initialize donut chart states with initial values (100% for all data)
        const totalHazards = hazardDetections.length;
        // TBC - ambil nilai dari elemen HTML yang sudah di-render dari PHP
        const statCctvCountElement = document.getElementById('statCctvCount');
        const totalTbc = statCctvCountElement ? parseInt(statCctvCountElement.textContent.replace(/,/g, '')) || 0 : 0;
        const totalInsiden = insidenDataset.length;
        const totalGr = hazardDetections.filter(function(h) {
            return h.nama_goldenrule && h.nama_goldenrule !== 'N/A' && h.nama_goldenrule !== '';
        }).length;
        
        // Set initial state - TBC tidak perlu animasi karena data statis
        donutChartState.donutHazard = totalHazards > 0 ? 100 : 0;
        donutChartState.donutCctv = 100; // TBC tetap 100% karena data statis
        donutChartState.donutInsiden = totalInsiden > 0 ? 100 : 0;
        donutChartState.donutGr = totalGr > 0 ? 100 : 0;
        
        // Initialize number animation states with initial values
        numberAnimationState.statHazardCount = totalHazards;
        numberAnimationState.statCctvCount = totalTbc; // Gunakan nilai TBC dari HTML
        numberAnimationState.statInsidenCount = totalInsiden;
        numberAnimationState.statGrCount = totalGr;
        
        // Initialize TBC donut chart dengan nilai 100% (data statis)
        updateDonutChart('donutCctv', 100, '#6f42c1');
        
        // Initialize statistics with no filter (all sites)
        updateStatisticsBySite('');
    }, 500);

    function highlightAreaKerjaForCCTV(cctv) {
        // Remove previous highlight
        if (highlightedAreaKerjaLayer) {
            map.removeLayer(highlightedAreaKerjaLayer);
            highlightedAreaKerjaLayer = null;
        }
        
        if (!areaKerjaBmo2PamaLayer) {
            return;
        }
        
        const cctvName = cctv.name || cctv.cctv_name || cctv.nama_cctv || '';
        const cctvNo = cctv.no_cctv || cctv.nomor_cctv || '';
        
        console.log('Searching area kerja for CCTV:', { cctvName, cctvNo, cctv });
        
        const matchingFeatures = [];
        
        // Helper function to normalize CCTV name for matching
        function normalizeCctvName(name) {
            if (!name) return '';
            return name.toLowerCase()
                .replace(/\s+/g, '')
                .replace(/[_-]/g, '')
                .trim();
        }
        
        // Method 1: Search in intersection layer (most accurate)
        if (intersectionBmo2PamaLayer) {
            const intersectionSource = intersectionBmo2PamaLayer.getSource();
            const intersectionFeatures = intersectionSource.getFeatures();
            
            console.log('Checking intersection layer, features:', intersectionFeatures.length);
            
            intersectionFeatures.forEach(function(feature) {
                const props = feature.getProperties();
                const featureCctvName = props.nama_cctv || '';
                const featureCctvNo = props.nomor_cctv || '';
                
                // Normalize names for better matching
                const normalizedCctvName = normalizeCctvName(cctvName);
                const normalizedFeatureName = normalizeCctvName(featureCctvName);
                
                // Match by CCTV name or number (more flexible matching)
                const nameMatch = (normalizedCctvName && normalizedFeatureName && 
                    (normalizedFeatureName.includes(normalizedCctvName) || 
                     normalizedCctvName.includes(normalizedFeatureName)));
                const numberMatch = (cctvNo && featureCctvNo && cctvNo === featureCctvNo);
                const partialMatch = (cctvName && featureCctvName && 
                    (featureCctvName.toLowerCase().includes(cctvName.toLowerCase()) ||
                     cctvName.toLowerCase().includes(featureCctvName.toLowerCase())));
                
                if (nameMatch || numberMatch || partialMatch) {
                    console.log('Found match in intersection:', { featureCctvName, featureCctvNo, props });
                    // Found intersection, now find corresponding area kerja
                    const idLokasi = props.id_lokasi;
                    const lokasi = props.lokasi;
                    
                    if (idLokasi || lokasi) {
                        const areaKerjaSource = areaKerjaBmo2PamaLayer.getSource();
                        const areaKerjaFeatures = areaKerjaSource.getFeatures();
                        
                        areaKerjaFeatures.forEach(function(areaKerjaFeature) {
                            const areaKerjaProps = areaKerjaFeature.getProperties();
                            if ((idLokasi && areaKerjaProps.id_lokasi === idLokasi) ||
                                (lokasi && areaKerjaProps.lokasi === lokasi)) {
                                if (!matchingFeatures.find(f => f === areaKerjaFeature)) {
                                    matchingFeatures.push(areaKerjaFeature);
                                }
                            }
                        });
                    }
                }
            });
        }
        
        // Method 2: Search in area CCTV layer and find overlapping area kerja
        if (matchingFeatures.length === 0 && areaCctvBmo2PamaLayer) {
            const areaCctvSource = areaCctvBmo2PamaLayer.getSource();
            const areaCctvFeatures = areaCctvSource.getFeatures();
            
            let cctvAreaFeature = null;
            areaCctvFeatures.forEach(function(feature) {
                const props = feature.getProperties();
                const featureCctvName = props.nama_cctv || '';
                const featureCctvNo = props.nomor_cctv || '';
                
                    const normalizedCctvName = normalizeCctvName(cctvName);
                    const normalizedFeatureName = normalizeCctvName(featureCctvName);
                    
                    const nameMatch = (normalizedCctvName && normalizedFeatureName && 
                        (normalizedFeatureName.includes(normalizedCctvName) || 
                         normalizedCctvName.includes(normalizedFeatureName)));
                    const numberMatch = (cctvNo && featureCctvNo && cctvNo === featureCctvNo);
                    const partialMatch = (cctvName && featureCctvName && 
                        (featureCctvName.toLowerCase().includes(cctvName.toLowerCase()) ||
                         cctvName.toLowerCase().includes(featureCctvName.toLowerCase())));
                    
                    if (nameMatch || numberMatch || partialMatch) {
                        console.log('Found CCTV area feature:', { featureCctvName, featureCctvNo });
                        cctvAreaFeature = feature;
                    }
            });
            
            // If found CCTV area, find overlapping area kerja
            if (cctvAreaFeature) {
                const cctvAreaGeometry = cctvAreaFeature.getGeometry();
                const areaKerjaSource = areaKerjaBmo2PamaLayer.getSource();
                const areaKerjaFeatures = areaKerjaSource.getFeatures();
                
                areaKerjaFeatures.forEach(function(areaKerjaFeature) {
                    const areaKerjaGeometry = areaKerjaFeature.getGeometry();
                    if (areaKerjaGeometry && cctvAreaGeometry) {
                        // Check if geometries intersect
                        if (areaKerjaGeometry.intersectsExtent(cctvAreaGeometry.getExtent())) {
                            // More precise check: get intersection
                            try {
                                const intersection = areaKerjaGeometry.intersection(cctvAreaGeometry);
                                if (intersection && !intersection.isEmpty()) {
                                    if (!matchingFeatures.find(f => f === areaKerjaFeature)) {
                                        matchingFeatures.push(areaKerjaFeature);
                                    }
                                }
                            } catch(e) {
                                // If intersection fails, use extent check
                                if (!matchingFeatures.find(f => f === areaKerjaFeature)) {
                                    matchingFeatures.push(areaKerjaFeature);
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // Method 3: Use CCTV location point if available
        if (matchingFeatures.length === 0 && cctv.location && Array.isArray(cctv.location) && cctv.location.length === 2) {
            const cctvPoint = ol.proj.fromLonLat(cctv.location);
            const areaKerjaSource = areaKerjaBmo2PamaLayer.getSource();
            const areaKerjaFeatures = areaKerjaSource.getFeatures();
            
            // Find area kerja that contains the CCTV location
            areaKerjaFeatures.forEach(function(feature) {
                const geometry = feature.getGeometry();
                if (geometry && geometry.intersectsCoordinate(cctvPoint)) {
                    if (!matchingFeatures.find(f => f === feature)) {
                        matchingFeatures.push(feature);
                    }
                }
            });
            
            // If no direct intersection, find nearest area kerja within 1000m
            if (matchingFeatures.length === 0) {
                let nearestFeature = null;
                let minDistance = Infinity;
                const cctvLonLat = ol.proj.toLonLat(cctvPoint);
                
                areaKerjaFeatures.forEach(function(feature) {
                    const geometry = feature.getGeometry();
                    if (geometry) {
                        const closestPoint = geometry.getClosestPoint(cctvPoint);
                        const closestLonLat = ol.proj.toLonLat(closestPoint);
                        const distance = ol.sphere.getDistance(cctvLonLat, closestLonLat);
                        
                        if (distance < 1000 && distance < minDistance) {
                            minDistance = distance;
                            nearestFeature = feature;
                        }
                    }
                });
                
                if (nearestFeature) {
                    matchingFeatures.push(nearestFeature);
                }
            }
        }
        
        console.log('Found matching area kerja features:', matchingFeatures.length);
        
        // Create highlight layer with matching features
        if (matchingFeatures.length > 0) {
            const highlightSource = new ol.source.Vector({
                features: matchingFeatures.map(function(feature) {
                    // Clone feature for highlight
                    const clonedFeature = feature.clone();
                    return clonedFeature;
                })
            });
            
            highlightedAreaKerjaLayer = new ol.layer.Vector({
                source: highlightSource,
                style: function(feature) {
                    const props = feature.getProperties();
                    const areaKerja = props.area_kerja || '';
                    
                    // Enhanced highlight style
                    let fillColor = 'rgba(59, 130, 246, 0.5)'; // Blue with more opacity
                    let strokeColor = '#3b82f6';
                    let strokeWidth = 3;
                    
                    if (areaKerja === 'Pit') {
                        fillColor = 'rgba(239, 68, 68, 0.5)'; // Red
                        strokeColor = '#ef4444';
                    } else if (areaKerja === 'Hauling') {
                        fillColor = 'rgba(245, 158, 11, 0.5)'; // Orange
                        strokeColor = '#f59e0b';
                    } else if (areaKerja === 'Infra Tambang') {
                        fillColor = 'rgba(59, 130, 246, 0.5)'; // Blue
                        strokeColor = '#3b82f6';
                    }
                    
                    return new ol.style.Style({
                        fill: new ol.style.Fill({
                            color: fillColor
                        }),
                        stroke: new ol.style.Stroke({
                            color: strokeColor,
                            width: strokeWidth,
                            lineDash: [10, 5] // Dashed line for highlight
                        })
                    });
                },
                zIndex: 1002, // Above CCTV but below hazard markers
                opacity: 0.9
            });
            
            map.addLayer(highlightedAreaKerjaLayer);
            
            // Fit map to show both CCTV and area kerja
            const extent = highlightedAreaKerjaLayer.getSource().getExtent();
            if (extent && extent[0] !== Infinity) {
                map.getView().fit(extent, {
                    padding: [50, 50, 50, 50],
                    duration: 500,
                    maxZoom: 17
                });
            }
        }
    }

    function showCCTVPopup(coordinate, cctv) {
        const cctvName = cctv.name || cctv.cctv_name || cctv.nama_cctv || 'CCTV';
        
        // Check if data is incomplete (missing no_cctv, site, or perusahaan)
        const hasNoCctv = (!cctv.no_cctv || cctv.no_cctv === 'N/A' || cctv.no_cctv === null) && 
                          (!cctv.nomor_cctv || cctv.nomor_cctv === 'N/A' || cctv.nomor_cctv === null);
        const hasNoSite = (!cctv.site || cctv.site === 'N/A' || cctv.site === null);
        const hasNoPerusahaan = (!cctv.perusahaan || cctv.perusahaan === 'N/A' || cctv.perusahaan === null) &&
                                 (!cctv.perusahaan_cctv || cctv.perusahaan_cctv === 'N/A' || cctv.perusahaan_cctv === null);
        
        const isDataIncomplete = hasNoCctv || hasNoSite || hasNoPerusahaan;
        
        // If data is incomplete, fetch from database
        if (isDataIncomplete && cctvName && cctvName !== 'CCTV') {
            // Show loading message
            document.getElementById('popup-content').innerHTML = `
                <div style="min-width: 250px; text-align: center; padding: 20px;">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0">Memuat data CCTV...</p>
                </div>
            `;
            popupOverlay.setPosition(coordinate);
            
            // Fetch data from API
            fetch('{{ route("hazard-detection.api.cctv") }}?name=' + encodeURIComponent(cctvName))
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        // Merge fetched data with existing data
                        const mergedCctv = { ...cctv, ...result.data };
                        displayCCTVPopupContent(coordinate, mergedCctv);
                    } else {
                        // If not found, display with available data
                        displayCCTVPopupContent(coordinate, cctv);
                    }
                })
                .catch(error => {
                    console.error('Error fetching CCTV data:', error);
                    // Display with available data on error
                    displayCCTVPopupContent(coordinate, cctv);
                });
        } else {
            // Data is complete, display directly
            displayCCTVPopupContent(coordinate, cctv);
        }
    }

    // Helper function untuk format tanggal dengan mengurangi 8 jam (UTC ke WIB)
    function formatDateWIB(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            // Kurangi 8 jam untuk konversi UTC ke WIB
            date.setHours(date.getHours() - 8);
            return date.toLocaleString('id-ID', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        } catch (e) {
            return 'N/A';
        }
    }

    function showUserGpsPopup(coordinate, user) {
        if (!user) {
            return;
        }

        const fullname = user.fullname || 'N/A';
        const npk = user.npk || 'N/A';
        const email = user.email || 'N/A';
        const phone = user.phone || 'N/A';
        const gender = user.gender || 'N/A';
        const division = user.division_name || 'N/A';
        const department = user.department_name || 'N/A';
        const functionalPosition = user.functional_position || 'N/A';
        const structuralPosition = user.structural_position || 'N/A';
        const siteAssignment = user.site_assignment || 'N/A';
        const course = user.course !== null && user.course !== undefined ? user.course + '°' : 'N/A';
        const battery = user.battery !== null && user.battery !== undefined ? user.battery + '%' : 'N/A';
        const batteryColor = user.battery < 20 ? '#ef4444' : user.battery < 50 ? '#f59e0b' : '#10b981';
        const updatedAt = formatDateWIB(user.gps_updated_at);
        const latitude = user.latitude !== null && user.latitude !== undefined ? user.latitude.toFixed(6) : 'N/A';
        const longitude = user.longitude !== null && user.longitude !== undefined ? user.longitude.toFixed(6) : 'N/A';

        // Tampilkan loading state dulu
        const loadingContent = `
            <div style="min-width: 280px;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="material-icons-outlined text-primary">person_pin</i>
                    <h6 style="margin: 0; font-weight: 600;">${fullname}</h6>
                </div>
                <div style="border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 10px;">
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>SID:</strong> ${npk}
                    </p>
                    <p style="margin: 5px 0; font-size: 13px; color: #6b7280;">
                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">sync</i>
                        Memuat informasi lokasi...
                    </p>
                </div>
            </div>
        `;

        document.getElementById('popup-content').innerHTML = loadingContent;
        popupOverlay.setPosition(coordinate);

        // Load location details
        const locationId = user.location_id || user.work_area_location_id || null;
        const employeeId = user.employee_id || user.user_id || user.id || null;
        
        fetch(`{{ route('maps.api.gps-user-location-details') }}?latitude=${latitude}&longitude=${longitude}${locationId ? '&location_id=' + locationId : ''}${employeeId ? '&employee_id=' + employeeId : ''}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const locationData = data.data;
                    const workAreaName = locationData.work_area ? locationData.work_area.name : 'Tidak diketahui';
                    const sapCount = locationData.sap_count || 0;
                    const sapOpenCount = locationData.sap_open_count || 0;
                    const cctvCount = locationData.cctv_count || 0;
                    const pjaCount = locationData.pja_count || 0;

                    const content = `
                        <div style="min-width: 300px;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="material-icons-outlined text-primary">person_pin</i>
                                <h6 style="margin: 0; font-weight: 600;">${fullname}</h6>
                            </div>
                            <div style="border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 10px;">
                                <p style="margin: 5px 0; font-size: 13px;">
                                    <strong>SID:</strong> ${npk}
                    </p>
                    ${email !== 'N/A' ? `<p style="margin: 5px 0; font-size: 13px;"><strong>Email:</strong> ${email}</p>` : ''}
                    ${phone !== 'N/A' ? `<p style="margin: 5px 0; font-size: 13px;"><strong>Phone:</strong> ${phone}</p>` : ''}
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>Jenis Kelamin:</strong> ${gender === 'L' ? 'Laki-laki' : gender === 'P' ? 'Perempuan' : gender}
                    </p>
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>Divisi:</strong> ${division}
                    </p>
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>Departemen:</strong> ${department}
                    </p>
                    ${functionalPosition !== 'N/A' ? `<p style="margin: 5px 0; font-size: 13px;"><strong>Jabatan Fungsional:</strong> ${functionalPosition}</p>` : ''}
                    ${structuralPosition !== 'N/A' ? `<p style="margin: 5px 0; font-size: 13px;"><strong>Jabatan Struktural:</strong> ${structuralPosition}</p>` : ''}
                    ${siteAssignment !== 'N/A' ? `<p style="margin: 5px 0; font-size: 13px;"><strong>Site Assignment:</strong> ${siteAssignment}</p>` : ''}
                                
                    <div style="border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 10px;">
                        <p style="margin: 5px 0; font-size: 13px;">
                            <strong>Koordinat:</strong> ${latitude}, ${longitude}
                        </p>
                        <p style="margin: 5px 0; font-size: 13px;">
                            <strong>Course:</strong> ${course}
                        </p>
                        <p style="margin: 5px 0; font-size: 13px;">
                            <strong>Battery:</strong> <span style="color: ${batteryColor};">${battery}</span>
                        </p>
                        <p style="margin: 5px 0; font-size: 13px;">
                            <strong>Update Terakhir:</strong> ${updatedAt}
                        </p>
                    </div>

                                <div style="border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 10px; background-color: #f9fafb; padding: 10px; border-radius: 6px;">
                                    <p style="margin: 5px 0; font-size: 13px; font-weight: 600; color: #1f2937;">
                                        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">location_on</i>
                                        Lokasi Saat Ini
                                    </p>
                                    <p style="margin: 5px 0; font-size: 13px;">
                                        <strong>Area Kerja:</strong> ${workAreaName}
                                    </p>
                                    <p style="margin: 5px 0; font-size: 13px;">
                                        <strong>SAP di Area:</strong> <span style="color: ${sapCount > 0 ? '#ef4444' : '#10b981'}; font-weight: 600;">${sapCount}</span>
                                    </p>
                                    <p style="margin: 5px 0; font-size: 13px;">
                                        <strong>SAP Open:</strong> <span style="color: ${sapOpenCount > 0 ? '#dc2626' : '#10b981'}; font-weight: 600;">${sapOpenCount}</span>
                                    </p>
                                    <p style="margin: 5px 0; font-size: 13px;">
                                        <strong>CCTV Mengcover:</strong> <span style="color: ${cctvCount > 0 ? '#3b82f6' : '#6b7280'}; font-weight: 600;">${cctvCount}</span>
                                    </p>
                                    <p style="margin: 5px 0; font-size: 13px;">
                                        <strong>PJA Lokasi:</strong> <span style="color: ${pjaCount > 0 ? '#8b5cf6' : '#6b7280'}; font-weight: 600;">${pjaCount}</span>
                                    </p>
                                </div>

                                <div style="margin-top: 10px;">
                                    <button class="btn btn-sm btn-primary w-100" onclick="showGpsUserDetailModal(${JSON.stringify(user).replace(/"/g, '&quot;')}, ${JSON.stringify(locationData).replace(/"/g, '&quot;')})">
                                        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">info</i>
                                        Lihat Detail Lengkap
                                    </button>
                                </div>
                </div>
            </div>
        `;

        document.getElementById('popup-content').innerHTML = content;
                } else {
                    // Fallback jika API error
                    showUserGpsPopupFallback(coordinate, user);
                }
            })
            .catch(error => {
                console.error('Error loading location details:', error);
                showUserGpsPopupFallback(coordinate, user);
            });
    }

    function showUserGpsPopupFallback(coordinate, user) {
        const fullname = user.fullname || 'N/A';
        const npk = user.npk || 'N/A';
        const latitude = user.latitude !== null && user.latitude !== undefined ? user.latitude.toFixed(6) : 'N/A';
        const longitude = user.longitude !== null && user.longitude !== undefined ? user.longitude.toFixed(6) : 'N/A';
        const workAreaName = user.work_area_name || 'Tidak diketahui';

        const content = `
            <div style="min-width: 280px;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="material-icons-outlined text-primary">person_pin</i>
                    <h6 style="margin: 0; font-weight: 600;">${fullname}</h6>
                </div>
                <div style="border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 10px;">
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>SID:</strong> ${npk}
                    </p>
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>Area Kerja:</strong> ${workAreaName}
                    </p>
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>Koordinat:</strong> ${latitude}, ${longitude}
                    </p>
                    <p style="margin: 5px 0; font-size: 12px; color: #6b7280;">
                        Gagal memuat detail lokasi
                    </p>
                </div>
            </div>
        `;

        document.getElementById('popup-content').innerHTML = content;
    }

    // Function to show GPS user detail modal
    window.showGpsUserDetailModal = function(user, locationData) {
        // Close popup first
        popupOverlay.setPosition(undefined);
        
        // Create or get modal element
        let modalElement = document.getElementById('gpsUserDetailModal');
        if (!modalElement) {
            modalElement = document.createElement('div');
            modalElement.id = 'gpsUserDetailModal';
            modalElement.className = 'modal fade';
            modalElement.setAttribute('tabindex', '-1');
            document.body.appendChild(modalElement);
    }

        const workAreaName = locationData.work_area ? locationData.work_area.name : 'Tidak diketahui';
        const sapCount = locationData.sap_count || 0;
        const sapOpenCount = locationData.sap_open_count || 0;
        const cctvCount = locationData.cctv_count || 0;
        const pjaCount = locationData.pja_count || 0;
        const sapList = locationData.sap_list || [];
        const sapOpenList = locationData.sap_open_list || [];
        const cctvList = locationData.cctv_list || [];
        const pjaList = locationData.pja_list || [];

        modalElement.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="material-icons-outlined">person_pin</i>
                            Detail Lokasi: ${user.fullname || 'N/A'}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="material-icons-outlined">location_on</i>
                                            Lokasi Saat Ini
                                        </h6>
                                        <p class="mb-1"><strong>Area Kerja:</strong></p>
                                        <p class="text-muted">${workAreaName}</p>
                                        ${locationData.work_area ? `
                                            <p class="mb-1"><strong>Location ID:</strong></p>
                                            <p class="text-muted">${locationData.work_area.location_id || 'N/A'}</p>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="material-icons-outlined">info</i>
                                            Statistik
                                        </h6>
                                        <p class="mb-1"><strong>SAP di Area:</strong></p>
                                        <p class="text-danger" style="font-size: 24px; font-weight: 600;">${sapCount}</p>
                                        <p class="mb-1"><strong>SAP Open:</strong></p>
                                        <p class="text-danger" style="font-size: 24px; font-weight: 600;">${sapOpenCount}</p>
                                        <p class="mb-1"><strong>CCTV Mengcover:</strong></p>
                                        <p class="text-primary" style="font-size: 24px; font-weight: 600;">${cctvCount}</p>
                                        <p class="mb-1"><strong>PJA Lokasi:</strong></p>
                                        <p class="text-purple" style="font-size: 24px; font-weight: 600; color: #8b5cf6;">${pjaCount}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        ${sapOpenList.length > 0 ? `
                            <div class="mb-3">
                                <h6><i class="material-icons-outlined">warning</i> Daftar SAP Open di Area (${sapOpenCount})</h6>
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Task Number</th>
                                                <th>Jenis</th>
                                                <th>Lokasi</th>
                                                <th>Tanggal</th>
                                                <th>Jarak</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${sapOpenList.map(sap => `
                                                <tr>
                                                    <td>${sap.task_number || 'N/A'}</td>
                                                    <td><span class="badge bg-danger">${sap.source_type || sap.jenis_laporan || 'SAP'}</span></td>
                                                    <td>${sap.lokasi || sap.detail_lokasi || 'N/A'}</td>
                                                    <td>${sap.tanggal ? formatDateWIB(sap.tanggal).split(',')[0] : 'N/A'}</td>
                                                    <td>${sap.distance ? sap.distance + ' m' : 'N/A'}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ` : ''}

                        ${sapList.length > 0 ? `
                            <div class="mb-3">
                                <h6><i class="material-icons-outlined">warning</i> Daftar Semua SAP di Area (${sapCount})</h6>
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Task Number</th>
                                                <th>Jenis</th>
                                                <th>Lokasi</th>
                                                <th>Tanggal</th>
                                                <th>Jarak</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${sapList.map(sap => `
                                                <tr>
                                                    <td>${sap.task_number || 'N/A'}</td>
                                                    <td><span class="badge bg-warning">${sap.source_type || sap.jenis_laporan || 'SAP'}</span></td>
                                                    <td>${sap.lokasi || sap.detail_lokasi || 'N/A'}</td>
                                                    <td>${sap.tanggal ? formatDateWIB(sap.tanggal).split(',')[0] : 'N/A'}</td>
                                                    <td>${sap.distance ? sap.distance + ' m' : 'N/A'}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ` : ''}

                        ${cctvList.length > 0 ? `
                            <div class="mb-3">
                                <h6><i class="material-icons-outlined">videocam</i> CCTV yang Mengcover Area (${cctvCount})</h6>
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Nama CCTV</th>
                                                <th>No CCTV</th>
                                                <th>Coverage Lokasi</th>
                                                <th>Jarak</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${cctvList.map(cctv => `
                                                <tr>
                                                    <td>${cctv.name || 'N/A'}</td>
                                                    <td>${cctv.no_cctv || 'N/A'}</td>
                                                    <td>${cctv.coverage_lokasi || 'N/A'}</td>
                                                    <td>${cctv.distance ? cctv.distance + ' m' : 'N/A'}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ` : ''}

                        ${pjaList.length > 0 ? `
                            <div class="mb-3">
                                <h6><i class="material-icons-outlined">assignment</i> PJA di Lokasi (${pjaCount})</h6>
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>PJA ID</th>
                                                <th>Nama PJA</th>
                                                <th>Lokasi</th>
                                                <th>Site</th>
                                                <th>Type</th>
                                                <th>Employee</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${pjaList.map(pja => `
                                                <tr>
                                                    <td>${pja.pja_id || 'N/A'}</td>
                                                    <td>${pja.nama_pja || 'N/A'}</td>
                                                    <td>${pja.lokasi || pja.detail_lokasi || 'N/A'}</td>
                                                    <td>${pja.site || 'N/A'}</td>
                                                    <td><span class="badge bg-info">${pja.pja_type_name || 'N/A'}</span></td>
                                                    <td>${pja.employee_name || pja.kode_sid || 'N/A'}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        `;

        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    };

    function showUnitVehiclePopup(coordinate, unit) {
        if (!unit) {
            return;
        }

        const vehicleName = unit.vehicle_name || 'N/A';
        const vehicleNumber = unit.vehicle_number || 'N/A';
        const vehicleType = unit.vehicle_type || 'Unknown';
        const vendorName = unit.vendor_name || 'N/A';
        const speed = unit.speed !== null && unit.speed !== undefined ? unit.speed + ' km/h' : 'N/A';
        const course = unit.course !== null && unit.course !== undefined ? unit.course + '°' : 'N/A';
        const battery = unit.battery !== null && unit.battery !== undefined ? unit.battery + '%' : 'N/A';
        const updatedAt = unit.updated_at ? new Date(unit.updated_at).toLocaleString('id-ID') : 'N/A';

        const content = `
            <div style="min-width: 250px;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="material-icons-outlined text-primary">directions_car</i>
                    <h6 style="margin: 0; font-weight: 600;">${vehicleNumber}</h6>
                </div>
                <div style="border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 10px;">
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>Tipe:</strong> ${vehicleType}
                    </p>
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>Vendor:</strong> ${vendorName}
                    </p>
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>Kecepatan:</strong> ${speed}
                    </p>
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>Arah:</strong> ${course}
                    </p>
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>Baterai:</strong> ${battery}
                    </p>
                    <p style="margin: 5px 0; font-size: 12px; color: #666;">
                        <strong>Update Terakhir:</strong> ${updatedAt}
                    </p>
                    <p style="margin: 5px 0; font-size: 12px; color: #666;">
                        <strong>Koordinat:</strong> ${unit.latitude?.toFixed(6)}, ${unit.longitude?.toFixed(6)}
                    </p>
                </div>
            </div>
        `;
        document.getElementById('popup-content').innerHTML = content;
        popupOverlay.setPosition(coordinate);
    }

    function displayCCTVPopupContent(coordinate, cctv) {
        const cctvName = cctv.name || cctv.cctv_name || cctv.nama_cctv || 'CCTV';
        const cctvSite = cctv.site || 'N/A';
        const cctvStatus = cctv.status || cctv.kondisi || 'N/A';
        const linkAkses = cctv.link_akses || cctv.externalUrl || '';
        const rawRtspUrl = (cctv.rtsp_url && cctv.rtsp_url.trim() !== '') ? cctv.rtsp_url.trim() : '';
        const effectiveRtspUrl = rawRtspUrl || defaultCctvRtspUrl || '';
        const hasRtspStream = effectiveRtspUrl !== '';
        const noCctv = cctv.no_cctv || cctv.nomor_cctv || 'N/A';
        const perusahaan = cctv.perusahaan || cctv.perusahaan_cctv || 'N/A';
        
        // Highlight area kerja for this CCTV
        highlightAreaKerjaForCCTV(cctv);
        
        let actionButtons = '';
        // Tombol Stream Video
        if (hasRtspStream) {
            actionButtons += `<button type="button" class="btn btn-sm btn-primary mt-2 btn-open-stream" style="width: 100%;" 
                data-cctv-name="${cctvName.replace(/"/g, '&quot;')}" 
                data-rtsp-url="${effectiveRtspUrl.replace(/"/g, '&quot;')}">
                <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">videocam</i>
                Stream Video
            </button>`;
        } else if (linkAkses) {
            actionButtons += `<a href="${linkAkses}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mt-2" style="width: 100%;">
                <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">open_in_new</i>
                Buka Link CCTV
            </a>`;
        }
        actionButtons += `<button type="button" class="btn btn-sm btn-warning mt-2 btn-view-incidents-popup" style="width: 100%;" 
            data-cctv-name="${cctvName.replace(/"/g, '&quot;')}" 
            data-cctv-id="${(cctv.id || cctvName).toString().replace(/"/g, '&quot;')}">
            <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">report_problem</i>
            Lihat Hazard Pelaporan
        </button>`;
        actionButtons += `<button type="button" class="btn btn-sm btn-info mt-2 btn-view-pja-popup" style="width: 100%;" 
            data-cctv-name="${cctvName.replace(/"/g, '&quot;')}" 
            data-cctv-id="${(cctv.id || cctvName).toString().replace(/"/g, '&quot;')}">
            <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">construction</i>
            Lihat PJA & Laporan
        </button>`;
        actionButtons += `<button type="button" class="btn btn-sm btn-primary mt-2 btn-view-cctv-detail" style="width: 100%;" 
            data-perusahaan="${perusahaan.replace(/"/g, '&quot;')}">
            <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">list</i>
            Detail CCTV Perusahaan
        </button>`;
        
        const statusBadge = cctvStatus === 'Live View' || cctvStatus === 'live' || cctvStatus === 'Baik'
            ? '<span class="badge bg-success">' + cctvStatus + '</span>' 
            : `<span class="badge bg-secondary">${cctvStatus}</span>`;
        
        const content = `
            <div style="min-width: 250px;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="material-icons-outlined text-primary">videocam</i>
                    <h6 style="margin: 0; font-weight: 600;">${cctvName}</h6>
                </div>
                <div style="border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 10px;">
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>No. CCTV:</strong> ${noCctv}
                    </p>
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>Site:</strong> ${cctvSite}
                    </p>
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>Perusahaan:</strong> ${perusahaan}
                    </p>
                    <p style="margin: 5px 0; font-size: 13px;">
                        <strong>Status:</strong> ${statusBadge}
                    </p>
                    ${cctv.lokasi_pemasangan ? `
                        <p style="margin: 5px 0; font-size: 12px; color: #666;">
                            <strong>Lokasi:</strong> ${cctv.lokasi_pemasangan}
                        </p>
                    ` : ''}
                    ${cctv.control_room ? `
                        <p style="margin: 5px 0; font-size: 12px; color: #666;">
                            <strong>Control Room:</strong> ${cctv.control_room}
                        </p>
                    ` : ''}
                    ${hasRtspStream ? `
                        <p style="margin: 5px 0; font-size: 12px; color: #666;">
                            <strong>RTSP:</strong> Tersedia
                        </p>
                    ` : ''}
                    ${actionButtons}
                </div>
            </div>
        `;
        document.getElementById('popup-content').innerHTML = content;
        popupOverlay.setPosition(coordinate);
        
        // Add event listener for view incidents button in popup
        setTimeout(function() {
            const viewIncidentsBtn = document.querySelector('.btn-view-incidents-popup');
            if (viewIncidentsBtn) {
                viewIncidentsBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const cctvName = this.getAttribute('data-cctv-name');
                    const cctvId = this.getAttribute('data-cctv-id');
                    viewCCTVIncidents(cctvName, cctvId, e);
                    popupOverlay.setPosition(undefined);
                });
            }
            
            // Add event listener for stream video button in popup
            const openStreamBtn = document.querySelector('.btn-open-stream');
            if (openStreamBtn) {
                openStreamBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const cctvName = this.getAttribute('data-cctv-name');
                    const rtspUrl = this.getAttribute('data-rtsp-url');
                    openCCTVStreamModal(cctvName, rtspUrl);
                    popupOverlay.setPosition(undefined);
                });
            }
            
            // Add event listener for view PJA button in popup
            const viewPjaBtn = document.querySelector('.btn-view-pja-popup');
            if (viewPjaBtn) {
                viewPjaBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const cctvName = this.getAttribute('data-cctv-name');
                    const cctvId = this.getAttribute('data-cctv-id');
                    viewCCTVPja(cctvName, cctvId, e);
                    popupOverlay.setPosition(undefined);
                });
            }
            
            // Add event listener for view CCTV detail button in popup
            const viewCctvDetailBtn = document.querySelector('.btn-view-cctv-detail');
            if (viewCctvDetailBtn) {
                viewCctvDetailBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const perusahaan = this.getAttribute('data-perusahaan');
                    openCctvDetailModal(perusahaan);
                    popupOverlay.setPosition(undefined);
                });
            }
        }, 100);
    }
    
    async function openCCTVStreamModal(cctvName, rtspUrl) {
        const modalTitle = document.getElementById('cctvStreamModalLabel');
        const streamFrame = document.getElementById('cctvStreamFrame');
        const streamVideo = document.getElementById('cctvStreamVideo');
        const streamLoading = document.getElementById('cctvStreamLoading');
        const modalElement = document.getElementById('cctvStreamModal');
        
        // Save current stream data for refresh functionality
        currentStreamData.cctvName = cctvName;
        currentStreamData.rtspUrl = rtspUrl || '';
        
        modalTitle.textContent = `${escapeHtml(cctvName)} - Live Stream`;
        
        // Hide all elements first
        if (streamFrame) streamFrame.style.display = 'none';
        if (streamVideo) streamVideo.style.display = 'none';
        if (streamLoading) {
            streamLoading.style.display = 'block';
            streamLoading.innerHTML = `
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 mb-0">Memuat stream video dari Python...</p>
                <small class="text-white-50 d-block">Pastikan aplikasi Python berjalan di localhost:5000</small>
            `;
        }
        
        // Reset video player if exists
        if (streamVideo) {
            resetStreamPlayer(streamVideo, streamLoading);
        }
        
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        // Build Python app URL with CCTV parameter
        // Format URL yang didukung:
        // 1. http://localhost:5000?cctv=CCTV_NAME&rtsp=RTSP_URL
        // 2. http://localhost:5000/stream?cctv=CCTV_NAME&rtsp=RTSP_URL
        // 3. http://localhost:5000/video?cctv=CCTV_NAME&rtsp=RTSP_URL
        // Sesuaikan dengan endpoint yang digunakan aplikasi Python Anda
        const pythonAppBaseUrl = pythonAppUrl || 'http://localhost:5000';
        // Jika aplikasi Python menggunakan endpoint khusus, ubah di sini:
        // const pythonStreamUrl = `${pythonAppBaseUrl}/stream?cctv=${encodeURIComponent(cctvName)}&rtsp=${encodeURIComponent(rtspUrl || '')}`;
        const pythonStreamUrl = `${pythonAppBaseUrl}?cctv=${encodeURIComponent(cctvName)}&rtsp=${encodeURIComponent(rtspUrl || '')}`;
        
        // Set iframe source
        if (streamFrame) {
            streamFrame.src = pythonStreamUrl;
            
            // Handle iframe load
            streamFrame.onload = function() {
                if (streamLoading) {
                    streamLoading.style.display = 'none';
                }
                streamFrame.style.display = 'block';
            };
            
            // Handle iframe error
            streamFrame.onerror = function() {
                if (streamLoading) {
                    streamLoading.style.display = 'block';
                    streamLoading.innerHTML = `
                        <div class="text-center text-white">
                            <i class="material-icons-outlined" style="font-size: 48px; color: #ef4444;">error_outline</i>
                            <p class="mt-2 mb-1">Gagal memuat stream dari Python</p>
                            <p class="small">Pastikan aplikasi Python berjalan di ${pythonAppBaseUrl}</p>
                            <button class="btn btn-sm btn-primary mt-2" onclick="refreshCurrentStream()">
                                <i class="material-icons-outlined me-1" style="font-size: 16px;">refresh</i>
                                Coba Lagi
                            </button>
                        </div>
                    `;
                }
                streamFrame.style.display = 'none';
            };
            
            // Timeout check (if iframe doesn't load within 5 seconds)
            setTimeout(function() {
                if (streamFrame.style.display === 'none' && streamLoading && streamLoading.style.display === 'block') {
                    // Check if iframe is actually loaded (might be cross-origin issue)
                    try {
                        const frameDoc = streamFrame.contentDocument || streamFrame.contentWindow.document;
                        // If we can access, it's loaded
                        if (streamLoading) streamLoading.style.display = 'none';
                        streamFrame.style.display = 'block';
                    } catch (e) {
                        // Cross-origin is expected, assume it's loading
                        // Give it more time or show the frame anyway
                        if (streamLoading) streamLoading.style.display = 'none';
                        streamFrame.style.display = 'block';
                    }
                }
            }, 3000);
        }
        
        // Cleanup when modal is closed
        modalElement.addEventListener('hidden.bs.modal', function handleModalHide() {
            if (streamFrame) {
                streamFrame.src = '';
                streamFrame.style.display = 'none';
            }
            if (streamVideo) {
                resetStreamPlayer(streamVideo, streamLoading);
            }
            modalElement.removeEventListener('hidden.bs.modal', handleModalHide);
        });
    }
    
    // Function to refresh Python stream
    function refreshPythonStream(cctvName, rtspUrl) {
        const streamFrame = document.getElementById('cctvStreamFrame');
        const streamLoading = document.getElementById('cctvStreamLoading');
        
        if (!streamFrame || !streamLoading) {
            return;
        }
        
        streamLoading.style.display = 'block';
        streamFrame.style.display = 'none';
        
        const pythonAppBaseUrl = pythonAppUrl || 'http://localhost:5000';
        // Gunakan format URL yang sama dengan openCCTVStreamModal
        // Jika aplikasi Python menggunakan endpoint khusus, ubah di sini juga:
        // const pythonStreamUrl = `${pythonAppBaseUrl}/stream?cctv=${encodeURIComponent(cctvName)}&rtsp=${encodeURIComponent(rtspUrl || '')}&t=${Date.now()}`;
        const pythonStreamUrl = `${pythonAppBaseUrl}?cctv=${encodeURIComponent(cctvName)}&rtsp=${encodeURIComponent(rtspUrl || '')}&t=${Date.now()}`;
        
        streamFrame.src = pythonStreamUrl;
        
        // Handle iframe load after refresh
        streamFrame.onload = function() {
            if (streamLoading) {
                streamLoading.style.display = 'none';
            }
            streamFrame.style.display = 'block';
        };
    }
    
    // Make refreshPythonStream globally accessible
    window.refreshPythonStream = refreshPythonStream;
    
    // Function to refresh current stream (called from refresh button in modal)
    function refreshCurrentStream() {
        if (!currentStreamData.cctvName) {
            console.warn('No stream data available to refresh');
            alert('Tidak ada data stream untuk di-refresh. Silakan tutup modal dan buka stream lagi.');
            return;
        }
        
        const streamLoading = document.getElementById('cctvStreamLoading');
        const streamFrame = document.getElementById('cctvStreamFrame');
        
        // Show loading state
        if (streamLoading) {
            streamLoading.style.display = 'block';
            streamLoading.innerHTML = `
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 mb-0">Memuat ulang stream video dari Python...</p>
                <small class="text-white-50 d-block">Mohon tunggu</small>
            `;
        }
        if (streamFrame) {
            streamFrame.style.display = 'none';
        }
        
        // Refresh the stream
        refreshPythonStream(currentStreamData.cctvName, currentStreamData.rtspUrl);
    }
    
    // Make refreshCurrentStream globally accessible
    window.refreshCurrentStream = refreshCurrentStream;
    
    function attachHlsStream(videoElement, playlistUrl, loadingElement) {
        if (typeof Hls !== 'undefined' && Hls.isSupported()) {
            if (currentHlsInstance) {
                currentHlsInstance.destroy();
            }
            currentHlsInstance = new Hls({
                enableWorker: true,
                lowLatencyMode: false,
                backBufferLength: 60,
            });
            currentHlsInstance.loadSource(playlistUrl);
            currentHlsInstance.attachMedia(videoElement);
            currentHlsInstance.on(Hls.Events.MANIFEST_PARSED, function() {
                loadingElement.style.display = 'none';
                videoElement.style.display = 'block';
                videoElement.play().catch(() => {});
            });
            currentHlsInstance.on(Hls.Events.ERROR, function(event, data) {
                console.warn('HLS error', data);
                if (data.fatal) {
                    currentHlsInstance.destroy();
                    currentHlsInstance = null;
                    loadingElement.style.display = 'block';
                    loadingElement.innerHTML = `
                        <div class="text-center text-white">
                            <i class="material-icons-outlined" style="font-size: 48px; color: #ef4444;">error_outline</i>
                            <p class="mt-2 mb-1">Stream terhenti</p>
                            <p class="small">Kesalahan fatal HLS: ${escapeHtml(data.type || 'Unknown')}</p>
                        </div>
                    `;
                }
            });
        } else if (videoElement.canPlayType('application/vnd.apple.mpegurl')) {
            videoElement.src = playlistUrl;
            videoElement.addEventListener('loadedmetadata', function handleLoaded() {
                loadingElement.style.display = 'none';
                videoElement.style.display = 'block';
                videoElement.play().catch(() => {});
                videoElement.removeEventListener('loadedmetadata', handleLoaded);
            });
            videoElement.addEventListener('error', function handleError() {
                loadingElement.style.display = 'block';
                loadingElement.innerHTML = `
                    <div class="text-center text-white">
                        <i class="material-icons-outlined" style="font-size: 48px; color: #ef4444;">error_outline</i>
                        <p class="mt-2 mb-1">Player tidak mendukung HLS</p>
                        <p class="small">Gunakan browser yang mendukung HLS native atau Hls.js.</p>
                    </div>
                `;
                videoElement.removeEventListener('error', handleError);
            });
        } else {
            loadingElement.style.display = 'block';
            loadingElement.innerHTML = `
                <div class="text-center text-white">
                    <i class="material-icons-outlined" style="font-size: 48px; color: #ef4444;">error_outline</i>
                    <p class="mt-2 mb-1">Browser tidak mendukung HLS</p>
                    <p class="small">Silakan gunakan browser modern (Chrome, Edge, Safari).</p>
                </div>
            `;
        }
    }
    
    function resetStreamPlayer(videoElement, loadingElement) {
        if (currentHlsInstance) {
            currentHlsInstance.destroy();
            currentHlsInstance = null;
        }
        videoElement.pause();
        videoElement.removeAttribute('src');
        videoElement.load();
        videoElement.style.display = 'none';
        loadingElement.style.display = 'none';
        loadingElement.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 mb-0">Menyiapkan stream HLS...</p>
            <small class="text-white-50 d-block">Pastikan koneksi RTSP dapat diakses server</small>
        `;
    }

    // Filter functionality (only if elements exist)
    const statusFilter = document.getElementById('statusFilter');
    const severityFilter = document.getElementById('severityFilter');
    const typeFilter = document.getElementById('typeFilter');
    
    if (statusFilter) {
        statusFilter.addEventListener('change', filterHazards);
    }
    if (severityFilter) {
        severityFilter.addEventListener('change', filterHazards);
    }
    if (typeFilter) {
        typeFilter.addEventListener('change', filterHazards);
    }

    function filterHazards() {
        const statusFilterEl = document.getElementById('statusFilter');
        const severityFilterEl = document.getElementById('severityFilter');
        const typeFilterEl = document.getElementById('typeFilter');
        
        if (!statusFilterEl || !severityFilterEl || !typeFilterEl) {
            return; // Filters not available
        }
        
        const statusFilter = statusFilterEl.value;
        const severityFilter = severityFilterEl.value;
        const typeFilter = typeFilterEl.value;

        const hazardItems = document.querySelectorAll('.hazard-item');
        hazardItems.forEach(function(item) {
            const hazardId = item.getAttribute('data-hazard-id');
            const hazard = hazardDetections.find(h => h.id === hazardId);
            
            let show = true;
            if (statusFilter !== 'all' && hazard.status !== statusFilter) show = false;
            if (severityFilter !== 'all' && hazard.severity !== severityFilter) show = false;
            if (typeFilter !== 'all' && hazard.type !== typeFilter) show = false;

            item.style.display = show ? 'block' : 'none';
        });
    }

    // Hazard item click handler
    document.querySelectorAll('.hazard-item').forEach(function(item) {
        item.addEventListener('click', function() {
            // Remove previous selection
            document.querySelectorAll('.hazard-item').forEach(i => i.classList.remove('selected'));
            
            // Add selection to clicked item
            this.classList.add('selected');
            
            // Center map on hazard
            const lat = parseFloat(this.getAttribute('data-lat'));
            const lng = parseFloat(this.getAttribute('data-lng'));
            map.getView().setCenter(ol.proj.fromLonLat([lng, lat]));
            map.getView().setZoom(16);
        });
    });

    // Function to handle photo error
    function handleHazardPhotoError(imgElement) {
        imgElement.style.display = 'none';
        const fallback = imgElement.parentElement.querySelector('.hazard-photo-fallback');
        if (fallback) {
            fallback.style.display = 'flex';
        }
    }
    
    // Function to load thumbnail photo for list view
    async function loadHazardThumbnail(container) {
        if (!container) return;
        
        const hazardId = container.getAttribute('data-hazard-id');
        const photoUrl = container.getAttribute('data-photo-url');
        const originalId = container.getAttribute('data-original-id');
        
        if (!photoUrl) {
            container.innerHTML = `
                <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                    <i class="material-icons-outlined text-muted" style="font-size: 32px;">image_not_supported</i>
                </div>
            `;
            return;
        }
        
        // Extract ID from photoUrl
        const urlMatch = photoUrl.match(/\/photoCar\/(\d+)/);
        if (!urlMatch) {
            container.innerHTML = `
                <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                    <i class="material-icons-outlined text-muted" style="font-size: 32px;">image</i>
                </div>
            `;
            return;
        }
        
        const photoId = urlMatch[1];
        
        try {
            // Fetch photos from API endpoint
            const apiUrl = '{{ route("hazard-detection.api.photos") }}?id=' + photoId;
            const response = await fetch(apiUrl);
            
            if (response.ok) {
                const result = await response.json();
                
                if (result.success && result.data.foto_temuan) {
                    const fotoTemuanUrl = result.data.foto_temuan;
                    container.innerHTML = `
                        <img src="${escapeHtml(fotoTemuanUrl)}" 
                             alt="Foto Temuan" 
                             class="rounded" 
                             style="width: 100%; height: 100%; object-fit: cover;"
                             onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'w-100 h-100 d-flex align-items-center justify-content-center\\'><i class=\\'material-icons-outlined text-muted\\' style=\\'font-size: 32px;\\'>broken_image</i></div>';">
                    `;
                    return;
                }
            }
        } catch (error) {
            console.warn('Error loading thumbnail:', error);
        }
        
        // Fallback: show placeholder
        container.innerHTML = `
            <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                <i class="material-icons-outlined text-muted" style="font-size: 32px;">image</i>
            </div>
        `;
    }
    
    // Load thumbnails for all hazard photos in list view
    document.addEventListener('DOMContentLoaded', function() {
        const photoContainers = document.querySelectorAll('.hazard-photo-container');
        photoContainers.forEach(function(container) {
            // Load thumbnail with slight delay to avoid blocking
            setTimeout(function() {
                loadHazardThumbnail(container);
            }, 100);
        });
    });

    // Action functions
    function viewHazardDetails(hazardId) {
        const hazard = hazardDetections.find(h => h.id === hazardId);
        if (!hazard) {
            alert('Hazard tidak ditemukan');
            return;
        }

        const modalContent = document.getElementById('hazardDetailContent');
        const modalTitle = document.getElementById('hazardDetailModalLabel');
        
        // Build title with badge
        const severityBadgeClass = hazard.severity === 'critical' ? 'bg-danger' : 
                                  hazard.severity === 'high' ? 'bg-warning' : 
                                  hazard.severity === 'medium' ? 'bg-info' : 'bg-secondary';
        const statusBadgeClass = hazard.status === 'active' ? 'bg-danger' : 'bg-success';
        const severityText = hazard.keparahan || hazard.severity || 'N/A';
        const statusText = hazard.status || 'N/A';
        
        modalTitle.innerHTML = `
            <div class="d-flex align-items-center gap-2">
                <i class="material-icons-outlined">warning</i>
                <span>Detail Hazard</span>
                <span class="badge ${severityBadgeClass} ms-2">${escapeHtml(severityText)}</span>
                <span class="badge ${statusBadgeClass}">${escapeHtml(statusText)}</span>
            </div>
        `;
        
        // Build photo URL - gunakan url_photo yang sudah benar dari controller
        // url_photo format: https://hseautomation.beraucoal.co.id/report/photoCar/{id}
        let photoCarUrl = null;
        if (hazard.url_photo) {
            photoCarUrl = hazard.url_photo;
        } else if (hazard.original_id) {
            // Fallback jika url_photo tidak ada
            photoCarUrl = 'https://hseautomation.beraucoal.co.id/report/photoCar/' + hazard.original_id;
        }
        const hasPhoto = photoCarUrl !== null && photoCarUrl !== '';
        
        // Build content HTML with improved layout
        let contentHTML = `
            <div class="row g-4 align-items-stretch">
                <!-- Left Column - Photos (Foto Temuan & Foto Penyelesaian) -->
                <div class="col-12 col-lg-5 d-flex">
                    ${hasPhoto ? `
                        <div class="card border-0 shadow-sm w-100 d-flex flex-column">
                            <div class="card-header bg-gradient bg-primary text-white d-flex align-items-center justify-content-between">
                                <h6 class="mb-0 d-flex align-items-center text-white">
                                    <i class="material-icons-outlined me-2">image</i> Foto Hazard
                                </h6>
                            </div>
                            <div class="card-body p-3 flex-grow-1" style="background: #f8f9fa;">
                                <div id="hazard-photos-container-${hazard.id}" class="d-flex flex-column gap-3">
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary spinner-border-sm" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 mb-0 text-muted small">Memuat foto...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ` : `
                        <div class="card border-0 shadow-sm w-100 d-flex flex-column">
                            <div class="card-body text-center py-5 flex-grow-1 d-flex align-items-center justify-content-center">
                                <div>
                                    <i class="material-icons-outlined" style="font-size: 64px; color: #6c757d;">image_not_supported</i>
                                    <p class="mt-3 text-muted mb-0">Gambar tidak tersedia</p>
                                </div>
                            </div>
                        </div>
                    `}
                </div>
                
                <!-- Right Column - Details -->
                <div class="col-12 col-lg-7 d-flex">
                    <div class="d-flex flex-column gap-3 w-100">
                        <!-- Basic Information -->
                        <div class="card border-0 shadow-sm flex-grow-1 d-flex flex-column">
                            <div class="card-header bg-gradient bg-primary text-white">
                                <h6 class="mb-0 d-flex align-items-center text-white">
                                    <i class="material-icons-outlined me-2">info</i> Informasi Dasar
                                </h6>
                            </div>
                            <div class="card-body flex-grow-1">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-primary bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-primary">tag</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">ID Hazard</small>
                                                <strong class="d-block">${escapeHtml(hazard.id)}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-warning bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-warning">category</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Jenis Ketidaksesuaian</small>
                                                <strong class="d-block">${escapeHtml(hazard.type || 'N/A')}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-danger bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-danger">priority_high</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Keparahan</small>
                                                <span class="badge ${severityBadgeClass}">${escapeHtml(severityText)}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-success">check_circle</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Status</small>
                                                <span class="badge ${statusBadgeClass}">${escapeHtml(statusText)}</span>
                                            </div>
                                        </div>
                                    </div>
                                    ${hazard.nama_kategori ? `
                                    <div class="col-12">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-info bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-info">label</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Kategori</small>
                                                <strong class="d-block">${escapeHtml(hazard.nama_kategori)}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    ` : ''}
                                    ${hazard.nama_goldenrule ? `
                                    <div class="col-12">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-warning bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-warning">rule</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Golden Rule</small>
                                                <strong class="d-block">${escapeHtml(hazard.nama_goldenrule)}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    ` : ''}
                                    <div class="col-12">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-secondary bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-secondary">description</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Deskripsi</small>
                                                <p class="mb-0" style="line-height: 1.6;">${escapeHtml(hazard.description || 'N/A')}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                       
                    </div>
                </div>
                <div class="col-12">
                     <!-- Location Information -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-gradient bg-success text-white">
                                <h6 class="mb-0 d-flex align-items-center text-white">
                                    <i class="material-icons-outlined me-2">location_on</i> Informasi Lokasi
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-1">
                                    ${hazard.site || hazard.nama_site ? `
                                    <div class="col-12">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-success">business</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Site</small>
                                                <strong class="d-block">${escapeHtml(hazard.site || hazard.nama_site)}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    ` : ''}
                                    ${hazard.nama_lokasi ? `
                                    <div class="col-12">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-success">place</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Lokasi</small>
                                                <strong class="d-block">${escapeHtml(hazard.nama_lokasi)}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    ` : ''}
                                    ${hazard.nama_detail_lokasi || hazard.lokasi_detail ? `
                                    <div class="col-12">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-success">location_city</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Detail Lokasi</small>
                                                <strong class="d-block">${escapeHtml(hazard.nama_detail_lokasi || hazard.lokasi_detail)}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    ` : ''}
                                    ${hazard.location && hazard.location.lat && hazard.location.lng ? `
                                    <div class="col-12">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-success">map</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Koordinat</small>
                                                <div class="d-flex gap-2">
                                                    <span class="badge bg-secondary">Lat: ${hazard.location.lat.toFixed(6)}</span>
                                                    <span class="badge bg-secondary">Lng: ${hazard.location.lng.toFixed(6)}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reporter & PIC Information -->
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-gradient bg-info text-white">
                                        <h6 class="mb-0 d-flex align-items-center text-white">
                                            <i class="material-icons-outlined me-2">person</i> Pelapor
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-start gap-3 mb-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-info bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-info">account_circle</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Nama Pelapor</small>
                                                <strong class="d-block">${escapeHtml(hazard.nama_pelapor || hazard.personnel_name || 'N/A')}</strong>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-info bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-info">schedule</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Tanggal Pelaporan</small>
                                                <strong class="d-block">${escapeHtml(hazard.detected_at || hazard.tanggal_pembuatan || 'N/A')}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            ${hazard.nama_pic ? `
                            <div class="col-12 col-md-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-gradient bg-warning text-dark">
                                        <h6 class="mb-0 d-flex align-items-center  text-white">
                                            <i class="material-icons-outlined me-2">person_pin</i> PIC
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-start gap-3 mb-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-warning bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-warning">verified_user</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Nama PIC</small>
                                                <strong class="d-block">${escapeHtml(hazard.nama_pic)}</strong>
                                            </div>
                                        </div>
                                        ${hazard.resolved_at ? `
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-warning bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-warning">done_all</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Tanggal Penyelesaian</small>
                                                <strong class="d-block">${escapeHtml(hazard.resolved_at)}</strong>
                                            </div>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                        </div>
                        
                        <!-- Risk Assessment & CCTV -->
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-gradient bg-danger text-white">
                                        <h6 class="mb-0 d-flex align-items-center  text-white">
                                            <i class="material-icons-outlined me-2">assessment</i> Penilaian Risiko
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-start gap-3 mb-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-danger bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-danger">priority_high</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Keparahan</small>
                                                <strong class="d-block">${escapeHtml(hazard.keparahan || 'N/A')}</strong>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-start gap-3 mb-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-danger bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-danger">repeat</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Kekerapan</small>
                                                <strong class="d-block">${escapeHtml(hazard.kekerapan || 'N/A')}</strong>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-danger bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-danger">calculate</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">Nilai Risiko</small>
                                                <span class="badge ${hazard.nilai_resiko ? 'bg-primary' : 'bg-secondary'} fs-6">
                                                    ${escapeHtml(hazard.nilai_resiko || 'N/A')}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-gradient bg-secondary text-white">
                                        <h6 class="mb-0 d-flex align-items-center  text-white">
                                            <i class="material-icons-outlined me-2">videocam</i> Informasi CCTV
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-secondary bg-opacity-10 rounded-circle p-2">
                                                    <i class="material-icons-outlined text-secondary">camera</i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted d-block">CCTV ID / Tools Observation</small>
                                                <strong class="d-block">${escapeHtml(hazard.cctv_id || 'N/A')}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                </div>
            </div>
        `;
        
        modalContent.innerHTML = contentHTML;
        
        // Show modal using Bootstrap modal
        const modal = new bootstrap.Modal(document.getElementById('hazardDetailModal'));
        modal.show();
        
        // Load photos from photoCar page if available
        if (hasPhoto && photoCarUrl) {
            loadHazardPhotos(hazard.id, photoCarUrl);
        }
    }
    
    // Function to load photos from photoCar page using API
    async function loadHazardPhotos(hazardId, photoCarUrl) {
        const container = document.getElementById(`hazard-photos-container-${hazardId}`);
        if (!container) return;
        
        // Extract ID from photoCarUrl
        const urlMatch = photoCarUrl.match(/\/photoCar\/(\d+)/);
        if (!urlMatch) {
            container.innerHTML = `
                <div class="alert alert-warning">
                    <i class="material-icons-outlined me-2">warning</i>
                    <span>URL foto tidak valid</span>
                </div>
            `;
            return;
        }
        
        const photoId = urlMatch[1];
        
        try {
            // Fetch photos from API endpoint
            const apiUrl = '{{ route("hazard-detection.api.photos") }}?id=' + photoId;
            const response = await fetch(apiUrl);
            
            if (!response.ok) {
                throw new Error('Failed to fetch photos from API');
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Failed to extract photos');
            }
            
            const fotoTemuanUrl = result.data.foto_temuan;
            const fotoPenyelesaianUrl = result.data.foto_penyelesaian;
            
            // Build HTML for photos
            let photosHTML = '';
            
            // Foto Temuan
            if (fotoTemuanUrl) {
                photosHTML += `
                    <div class="mb-3">
                        <h6 class="mb-2 d-flex align-items-center">
                            <i class="material-icons-outlined me-2 text-danger" style="font-size: 20px;">camera_alt</i>
                            <span>Foto Temuan</span>
                        </h6>
                        <div class="border rounded p-2 bg-white" style="min-height: 200px;">
                            <img src="${escapeHtml(fotoTemuanUrl)}" 
                                 alt="Foto Temuan" 
                                 class="img-fluid w-100 rounded" 
                                 style="max-height: 400px; object-fit: contain; cursor: pointer;"
                                 onclick="window.open('${escapeHtml(fotoTemuanUrl)}', '_blank')"
                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'text-center py-4 text-muted\\'><i class=\\'material-icons-outlined\\'>broken_image</i><p class=\\'mt-2 mb-0 small\\'>Gagal memuat foto temuan</p></div>';">
                        </div>
                    </div>
                `;
            } else {
                photosHTML += `
                    <div class="mb-3">
                        <h6 class="mb-2 d-flex align-items-center">
                            <i class="material-icons-outlined me-2 text-danger" style="font-size: 20px;">camera_alt</i>
                            <span>Foto Temuan</span>
                        </h6>
                        <div class="border rounded p-4 bg-white text-center">
                            <i class="material-icons-outlined text-muted" style="font-size: 48px;">image_not_supported</i>
                            <p class="mt-2 mb-0 text-muted small">Foto temuan tidak tersedia</p>
                        </div>
                    </div>
                `;
            }
            
            // Foto Penyelesaian
            if (fotoPenyelesaianUrl) {
                photosHTML += `
                    <div>
                        <h6 class="mb-2 d-flex align-items-center">
                            <i class="material-icons-outlined me-2 text-success" style="font-size: 20px;">check_circle</i>
                            <span>Foto Penyelesaian</span>
                        </h6>
                        <div class="border rounded p-2 bg-white" style="min-height: 200px;">
                            <img src="${escapeHtml(fotoPenyelesaianUrl)}" 
                                 alt="Foto Penyelesaian" 
                                 class="img-fluid w-100 rounded" 
                                 style="max-height: 400px; object-fit: contain; cursor: pointer;"
                                 onclick="window.open('${escapeHtml(fotoPenyelesaianUrl)}', '_blank')"
                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'text-center py-4 text-muted\\'><i class=\\'material-icons-outlined\\'>broken_image</i><p class=\\'mt-2 mb-0 small\\'>Gagal memuat foto penyelesaian</p></div>';">
                        </div>
                    </div>
                `;
            } else {
                photosHTML += `
                    <div>
                        <h6 class="mb-2 d-flex align-items-center">
                            <i class="material-icons-outlined me-2 text-success" style="font-size: 20px;">check_circle</i>
                            <span>Foto Penyelesaian</span>
                        </h6>
                        <div class="border rounded p-4 bg-white text-center">
                            <i class="material-icons-outlined text-muted" style="font-size: 48px;">image_not_supported</i>
                            <p class="mt-2 mb-0 text-muted small">Foto penyelesaian belum tersedia</p>
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = photosHTML;
            
        } catch (error) {
            console.error('Error loading photos:', error);
            container.innerHTML = `
                <div class="alert alert-warning">
                    <i class="material-icons-outlined me-2">warning</i>
                    <div>
                        <p class="mb-2">Gagal memuat foto dari halaman photoCar.</p>
                        <p class="mb-2 small text-muted">Error: ${escapeHtml(error.message || 'Unknown error')}</p>
                        <p class="mb-0">Silakan buka link berikut untuk melihat foto:</p>
                        <a href="${escapeHtml(photoCarUrl)}" target="_blank" class="mt-2 d-inline-block">
                            ${escapeHtml(photoCarUrl)}
                        </a>
                    </div>
                </div>
            `;
        }
    }

    function resolveHazard(hazardId) {
        if (confirm('Are you sure you want to resolve this hazard?')) {
            // Here you would make an API call to resolve the hazard
            console.log('Resolving hazard:', hazardId);
            alert('Hazard resolved successfully!');
            // Reload page or update UI
            location.reload();
        }
    }

    function getInsidenData(noKecelakaan) {
        if (!noKecelakaan) {
            return null;
        }

        return insidenDatasetMap.get(noKecelakaan) || null;
    }

    function focusInsidenOnMap(noKecelakaan) {
        const insiden = getInsidenData(noKecelakaan);
        if (!insiden || !insiden.longitude || !insiden.latitude) {
            return;
        }

        switchView('insiden');

        const coordinate = ol.proj.fromLonLat([parseFloat(insiden.longitude), parseFloat(insiden.latitude)]);
        map.getView().animate({ center: coordinate, zoom: 16, duration: 600 });
        showInsidenPopup(coordinate, insiden);
    }

    function openInsidenModal(noKecelakaan) {
        const insiden = getInsidenData(noKecelakaan);
        if (!insiden) {
            return;
        }

        const modalTitle = document.getElementById('insidenDetailModalLabel');
        const modalContent = document.getElementById('insidenDetailContent');
        modalTitle.textContent = `Detail Insiden - ${insiden.no_kecelakaan}`;

        let rows = '';
        if (insiden.items && insiden.items.length) {
            rows = insiden.items.map(function(item, index) {
                return `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${item.layer || '-'}</td>
                        <td>${item.jenis_item_ipls || '-'}</td>
                        <td>${item.detail_layer || '-'}</td>
                        <td>${item.klasifikasi_layer || '-'}</td>
                        <td>${item.keterangan_layer || '-'}</td>
                    </tr>
                `;
            }).join('');
        }

        modalContent.innerHTML = `
            <div class="mb-3">
                <p class="mb-1"><strong>Site:</strong> ${insiden.site || '-'}</p>
                <p class="mb-1"><strong>Lokasi:</strong> ${insiden.lokasi || '-'}</p>
                <p class="mb-1"><strong>Status LPI:</strong> ${insiden.status_lpi || '-'}</p>
                <p class="mb-1"><strong>Kategori:</strong> ${insiden.kategori || '-'}</p>
            </div>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Layer</th>
                            <th>Jenis Item IPLS</th>
                            <th>Detail Layer</th>
                            <th>Klasifikasi</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows || '<tr><td colspan="6" class="text-center text-muted">Tidak ada detail tersedia</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;

        bootstrap.Modal.getOrCreateInstance(document.getElementById('insidenDetailModal')).show();
    }

    // Function to add WMS layer to map
    function addWMSLayerToMap(layerName = '', serverKey = currentWmsServer) {
        // Remove existing WMS layer if any
        if (wmsLayer) {
            map.removeLayer(wmsLayer);
            wmsLayer = null;
        }
        
        // Create and add new WMS layer
        wmsLayer = createWMSLayer(layerName, serverKey);
        map.addLayer(wmsLayer);
        currentLayer = layerName;
        
        // Update map center berdasarkan server yang dipilih
        const server = wmsServers[serverKey];
        const view = map.getView();
        view.setCenter(ol.proj.fromLonLat(server.center));
        view.setZoom(15);
        
        // Pastikan hazard dan CCTV layer selalu di atas WMS layer
        const layers = map.getLayers();
        if (hazardLayer) {
            if (layers.getArray().includes(hazardLayer)) {
                layers.remove(hazardLayer);
            }
            layers.push(hazardLayer);
        }
        if (insidenLayer) {
            if (layers.getArray().includes(insidenLayer)) {
                layers.remove(insidenLayer);
            }
            layers.push(insidenLayer);
        }
        if (cctvLayer) {
            if (layers.getArray().includes(cctvLayer)) {
                layers.remove(cctvLayer);
            }
            layers.push(cctvLayer);
        }
        if (unitVehicleLayer) {
            if (layers.getArray().includes(unitVehicleLayer)) {
                layers.remove(unitVehicleLayer);
            }
            layers.push(unitVehicleLayer);
        }
        // Ensure BMO2 PAMA layers are above WMS but below hazard and CCTV
        if (areaKerjaBmo2PamaLayer && layers.getArray().includes(areaKerjaBmo2PamaLayer)) {
            layers.remove(areaKerjaBmo2PamaLayer);
            layers.push(areaKerjaBmo2PamaLayer);
        }
        if (areaCctvBmo2PamaLayer && layers.getArray().includes(areaCctvBmo2PamaLayer)) {
            layers.remove(areaCctvBmo2PamaLayer);
            layers.push(areaCctvBmo2PamaLayer);
        }
        if (differenceBmo2PamaLayer && layers.getArray().includes(differenceBmo2PamaLayer)) {
            layers.remove(differenceBmo2PamaLayer);
            layers.push(differenceBmo2PamaLayer);
        }
        if (symmetricalDifferenceBmo2PamaLayer && layers.getArray().includes(symmetricalDifferenceBmo2PamaLayer)) {
            layers.remove(symmetricalDifferenceBmo2PamaLayer);
            layers.push(symmetricalDifferenceBmo2PamaLayer);
        }
        if (intersectionBmo2PamaLayer && layers.getArray().includes(intersectionBmo2PamaLayer)) {
            layers.remove(intersectionBmo2PamaLayer);
            layers.push(intersectionBmo2PamaLayer);
        }
    }

    // Update layer when layer select changes (only if element exists)
    const layerSelect = document.getElementById('layerSelect');
    if (layerSelect) {
        layerSelect.addEventListener('change', function(e) {
            const selectedLayer = e.target.value;
            addWMSLayerToMap(selectedLayer);
        });
    }

    // Update WMS server when server select changes (only if element exists)
    const wmsServerSelect = document.getElementById('wmsServerSelect');
    if (wmsServerSelect) {
        wmsServerSelect.addEventListener('change', function(e) {
            currentWmsServer = e.target.value;
            wmsUrl = wmsServers[currentWmsServer].url;
            
            // Reload layers dari server yang baru
            loadWMSLayers();
        });
    }

    // Update projection when projection select changes (only if element exists)
    const projectionSelect = document.getElementById('projectionSelect');
    if (projectionSelect) {
        projectionSelect.addEventListener('change', function(e) {
            const projection = e.target.value;
            const view = map.getView();
            
            const server = wmsServers[currentWmsServer];
            const newCenter = server.center;
            
            if (projection === 'EPSG:4326') {
                view.setProjection('EPSG:4326');
                view.setCenter(newCenter);
                view.setZoom(15);
            } else {
                view.setProjection('EPSG:3857');
                view.setCenter(ol.proj.fromLonLat(newCenter));
                view.setZoom(15);
            }
        });
    }

    // Try to get capabilities to list available layers
    async function loadWMSLayers(serverKey = currentWmsServer) {
        const layerSelect = document.getElementById('layerSelect');
        if (!layerSelect) {
            return; // Layer select not available
        }
        layerSelect.innerHTML = '<option value="">Loading...</option>';
        
        const server = wmsServers[serverKey];
        const serverUrl = server.url;
        
        try {
            const capabilitiesUrl = serverUrl + '?SERVICE=WMS&VERSION=1.1.1&REQUEST=GetCapabilities';
            const response = await fetch(capabilitiesUrl);
            
            if (!response.ok) {
                throw new Error('Failed to fetch capabilities');
            }
            
            const text = await response.text();
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(text, 'text/xml');
            
            // Check for parsing errors
            const parseError = xmlDoc.querySelector('parsererror');
            if (parseError) {
                throw new Error('XML parsing error');
            }
            
            // Check for service exception
            const serviceException = xmlDoc.querySelector('ServiceException');
            if (serviceException) {
                throw new Error('Service Exception: ' + serviceException.textContent);
            }
            
            // Parse layer names
            const layers = xmlDoc.querySelectorAll('Layer > Name');
            layerSelect.innerHTML = '';
            
            if (layers.length > 0) {
                layers.forEach((layer) => {
                    const layerName = layer.textContent.trim();
                    const layerElement = layer.closest('Layer');
                    const titleElement = layerElement.querySelector('Title');
                    const layerTitle = titleElement ? titleElement.textContent.trim() : layerName;
                    
                    if (layerName) {
                        const option = document.createElement('option');
                        option.value = layerName;
                        option.textContent = layerTitle || `Layer: ${layerName}`;
                        layerSelect.appendChild(option);
                    }
                });
                
                // Auto-select first layer
                if (layers.length > 0) {
                    const firstLayerName = layers[0].textContent.trim();
                    layerSelect.value = firstLayerName;
                    addWMSLayerToMap(firstLayerName, serverKey);
                }
            } else {
                // Fallback jika tidak ada layer ditemukan
                const server = wmsServers[serverKey];
                layerSelect.innerHTML = `<option value="0">${server.name} - Layer 0</option>`;
                addWMSLayerToMap('0', serverKey);
            }
        } catch (error) {
            console.warn('Could not load WMS capabilities:', error);
            console.info('Using default layer "0"');
            
            // Fallback ke layer 0
            const server = wmsServers[serverKey];
            layerSelect.innerHTML = `<option value="0">${server.name} - Layer 0</option>`;
            layerSelect.value = '0';
            addWMSLayerToMap('0', serverKey);
        }
    }

    // Load layers on page load (only if layer select exists)
    if (document.getElementById('layerSelect')) {
        loadWMSLayers();
    }

    // Handle WMS errors
    function setupErrorHandling() {
        if (wmsLayer) {
            wmsLayer.getSource().on('tileloaderror', function(event) {
                console.error('Tile load error:', event);
                console.info('Trying alternative layer names or checking server configuration might help.');
            });
        }
    }

    // Setup error handling after WMS layer is added
    map.on('rendercomplete', function() {
        setupErrorHandling();
    });

    // View Switcher Function
    function switchView(viewType) {
        const hazardListView = document.getElementById('hazardListView');
        const cctvListView = document.getElementById('cctvListView');
        const insidenListView = document.getElementById('insidenListView');
        const grListView = document.getElementById('grListView');
        const unitListView = document.getElementById('unitListView');
        const pythonAppView = document.getElementById('pythonAppView');
        const cardTitle = document.getElementById('cardTitle');
        const btnResetFilter = document.getElementById('btnResetFilter');
        const cctvStreamContainer = document.getElementById('cctvStreamContainer');

        if (hazardListView) {
            hazardListView.style.display = viewType === 'hazard' ? 'block' : 'none';
        }
        if (cctvListView) {
            cctvListView.style.display = viewType === 'cctv' ? 'block' : 'none';
        }
        if (insidenListView) {
            insidenListView.style.display = viewType === 'insiden' ? 'block' : 'none';
        }
        if (grListView) {
            grListView.style.display = viewType === 'gr' ? 'block' : 'none';
        }
        if (unitListView) {
            unitListView.style.display = viewType === 'unit' ? 'block' : 'none';
        }
        if (pythonAppView) {
            pythonAppView.style.display = viewType === 'python' ? 'block' : 'none';
        }

        if (btnResetFilter) {
            btnResetFilter.style.display = viewType === 'hazard' ? 'inline-block' : 'none';
        }


        if (viewType === 'hazard') {
            cardTitle.textContent = 'Laporan Hazard Beats';
            resetHazardFilter();
        } else if (viewType === 'cctv') {
            cardTitle.textContent = 'CCTV Stream';
            if (cctvLocations && cctvLocations.length > 0 && cctvStreamContainer && cctvStreamContainer.children.length === 0) {
                renderCCTVStreams();
            }
        } else if (viewType === 'insiden') {
            cardTitle.textContent = 'Insiden Safety';
        } else if (viewType === 'gr') {
            cardTitle.textContent = 'GR (Golden Rule)';
        } else if (viewType === 'unit') {
            cardTitle.textContent = 'Unit Kendaraan';
            
            // If unitVehicles is empty, try to refresh data first
            if (!unitVehicles || unitVehicles.length === 0) {
                console.log('Unit vehicles data is empty, refreshing...');
                if (typeof window.refreshUnitVehicles === 'function') {
                    window.refreshUnitVehicles().then(function() {
                        if (typeof window.renderUnitList === 'function') {
                            window.renderUnitList();
                        }
                    });
                } else {
                    // Wait a bit if function not yet defined
                    setTimeout(function() {
                        if (typeof window.refreshUnitVehicles === 'function') {
                            window.refreshUnitVehicles().then(function() {
                                if (typeof window.renderUnitList === 'function') {
                                    window.renderUnitList();
                                }
                            });
                        }
                    }, 100);
                }
            } else {
                // Data exists, render immediately
                if (typeof window.renderUnitList === 'function') {
                    window.renderUnitList();
                } else {
                    // Wait a bit if function not yet defined
                    setTimeout(function() {
                        if (typeof window.renderUnitList === 'function') {
                            window.renderUnitList();
                        }
                    }, 100);
                }
            }
        } else if (viewType === 'python') {
            cardTitle.textContent = 'Python Application';
            // Check if Python app is accessible
            checkPythonAppConnection();
        }

        const viewSelector = document.getElementById('viewSelector');
        if (viewSelector && viewSelector.value !== viewType) {
            viewSelector.value = viewType;
        }
    }
    
    // Function to render unit list (exposed globally)
    window.renderUnitList = function() {
        const unitListView = document.getElementById('unitListView');
        
        if (!unitListView) {
            console.warn('unitListView element not found');
            return;
        }
        
        console.log('Rendering unit list, unitVehicles count:', unitVehicles ? unitVehicles.length : 0);
        
        if (!unitVehicles || unitVehicles.length === 0) {
            console.warn('No unit vehicles data available');
            unitListView.innerHTML = '<div class="text-center text-muted py-4">Tidak ada data unit kendaraan</div>';
            return;
        }
        
        // Render unit list view
        let html = '<div class="d-flex flex-column gap-3">';
        unitVehicles.forEach(function(unit, index) {
            const unitId = unit.unit_id || unit.id || unit.integration_id || index;
            const vehicleName = unit.vehicle_name || 'N/A';
            const vehicleNumber = unit.vehicle_number || 'N/A';
            const vehicleType = unit.vehicle_type || 'Unknown';
            const vendorName = unit.vendor_name || 'N/A';
            const speed = unit.speed !== null && unit.speed !== undefined ? unit.speed + ' km/h' : 'N/A';
            const battery = unit.battery !== null && unit.battery !== undefined ? unit.battery + '%' : 'N/A';
            const updatedAt = unit.updated_at ? new Date(unit.updated_at).toLocaleString('id-ID') : 'N/A';
            const latitude = unit.latitude || 0;
            const longitude = unit.longitude || 0;
            const hasCoordinates = latitude && longitude && latitude !== 0 && longitude !== 0;
            
            // Determine color based on vehicle type
            let typeColor = 'bg-primary';
            if (vehicleType.toLowerCase().includes('dump') || vehicleType.toLowerCase().includes('truck')) {
                typeColor = 'bg-warning';
            } else if (vehicleType.toLowerCase().includes('prime') || vehicleType.toLowerCase().includes('mover')) {
                typeColor = 'bg-success';
            } else if (vehicleType.toLowerCase().includes('lube')) {
                typeColor = 'bg-info';
            }
            
            html += `
                <div class="card border-0 shadow-sm unit-item" 
                     data-unit-id="${unitId}"
                     data-latitude="${latitude}"
                     data-longitude="${longitude}"
                     ${hasCoordinates ? 'onclick="focusUnitOnMap(\'' + unitId + '\')" style="cursor: pointer; transition: transform 0.2s;"' : 'style="transition: transform 0.2s;"'}>
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start justify-content-between">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <h6 class="mb-0">${vehicleNumber}</h6>
                                </div>
                                <div class="row g-2 small text-muted">
                                    <div class="col-6">
                                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">category</i>
                                        <span>Tipe: ${vehicleType}</span>
                                    </div>
                                    <div class="col-6">
                                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">business</i>
                                        <span>${vendorName}</span>
                                    </div>
                                    <div class="col-6">
                                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">speed</i>
                                        <span>${speed}</span>
                                    </div>
                                    <div class="col-6">
                                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">battery_charging_full</i>
                                        <span>${battery}</span>
                                    </div>
                                    <div class="col-12">
                                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">schedule</i>
                                        <span>Update: ${updatedAt}</span>
                                    </div>
                                    ${hasCoordinates ? `
                                    <div class="col-12">
                                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">location_on</i>
                                        <span>${parseFloat(latitude).toFixed(6)}, ${parseFloat(longitude).toFixed(6)}</span>
                                    </div>
                                    ` : `
                                    <div class="col-12">
                                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle; color: #dc3545;">location_off</i>
                                        <span class="text-danger">Tidak ada data lokasi</span>
                                    </div>
                                    `}
                                </div>
                            </div>
                            <div class="ms-2">
                                <button class="btn btn-sm ${hasCoordinates ? 'btn-outline-primary' : 'btn-outline-secondary'}" 
                                        ${hasCoordinates ? `onclick="event.stopPropagation(); focusUnitOnMap('${unitId}')"` : 'disabled title="Unit tidak memiliki data koordinat"'}
                                        style="cursor: ${hasCoordinates ? 'pointer' : 'not-allowed'};">
                                    <i class="material-icons-outlined" style="font-size: 16px;">map</i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        unitListView.innerHTML = html;
        
        // Add hover effect
        const unitItems = unitListView.querySelectorAll('.unit-item');
        unitItems.forEach(function(item) {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
            });
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
            });
        });
    };
    
    // Function to focus unit on map (exposed globally)
    window.focusUnitOnMap = function(unitId) {
        if (!unitId) return;
        
        // Find unit in unitVehicles array
        const unit = unitVehicles.find(function(u) {
            const id = u.unit_id || u.id || u.integration_id;
            return id == unitId;
        });
        
        if (!unit) {
            console.warn('Unit not found:', unitId);
            alert('Unit tidak ditemukan');
            return;
        }
        
        if (!unit.latitude || !unit.longitude || unit.latitude === 0 || unit.longitude === 0) {
            console.warn('Unit does not have valid coordinates:', unitId);
            alert('Unit ini tidak memiliki data koordinat untuk ditampilkan di peta');
            return;
        }
        
        // Find feature on map
        const source = unitVehicleLayer.getSource();
        const features = source.getFeatures();
        let targetFeature = null;
        
        features.forEach(function(feature) {
            const featureUnitId = feature.get('unitId');
            if (featureUnitId == unitId) {
                targetFeature = feature;
            }
        });
        
        if (targetFeature) {
            const coordinate = targetFeature.getGeometry().getCoordinates();
            const view = map.getView();
            
            // Animate to unit location
            view.animate({
                center: coordinate,
                zoom: 17,
                duration: 600
            });
            
            // Show popup
            setTimeout(function() {
                const unitData = targetFeature.get('unitData');
                showUnitVehiclePopup(coordinate, unitData);
            }, 300);
            
            // Highlight the unit (optional: add pulse effect)
            const unitItems = document.querySelectorAll('.unit-item');
            unitItems.forEach(function(item) {
                if (item.getAttribute('data-unit-id') == unitId) {
                    item.style.border = '2px solid #3b82f6';
                    setTimeout(function() {
                        item.style.border = '';
                    }, 2000);
                }
            });
        } else {
            // If feature not found, create coordinate from unit data
            const coordinate = ol.proj.fromLonLat([parseFloat(unit.longitude), parseFloat(unit.latitude)]);
            const view = map.getView();
            
            view.animate({
                center: coordinate,
                zoom: 17,
                duration: 600
            });
            
            setTimeout(function() {
                showUnitVehiclePopup(coordinate, unit);
            }, 300);
        }
    };
    

    // Function to check Python app connection
    function checkPythonAppConnection() {
        const pythonAppFrame = document.getElementById('pythonAppFrame');
        const pythonAppLoading = document.getElementById('pythonAppLoading');
        const pythonAppError = document.getElementById('pythonAppError');
        
        if (!pythonAppFrame || !pythonAppLoading || !pythonAppError) {
            return;
        }

        // Show loading
        pythonAppLoading.style.display = 'block';
        pythonAppError.style.display = 'none';
        pythonAppFrame.style.display = 'none';

        // Try to load the iframe
        pythonAppFrame.onload = function() {
            pythonAppLoading.style.display = 'none';
            pythonAppError.style.display = 'none';
            pythonAppFrame.style.display = 'block';
        };

        pythonAppFrame.onerror = function() {
            pythonAppLoading.style.display = 'none';
            pythonAppError.style.display = 'block';
            pythonAppFrame.style.display = 'none';
        };

        // Set timeout to check if frame loads
        setTimeout(function() {
            try {
                // Try to access frame content (will fail if cross-origin)
                const frameDoc = pythonAppFrame.contentDocument || pythonAppFrame.contentWindow.document;
                pythonAppLoading.style.display = 'none';
                pythonAppError.style.display = 'none';
                pythonAppFrame.style.display = 'block';
            } catch (e) {
                // Cross-origin error is expected, frame is loading
                pythonAppLoading.style.display = 'none';
                pythonAppError.style.display = 'none';
                pythonAppFrame.style.display = 'block';
            }
        }, 2000);
    }

    // Function to refresh Python app
    // Function to view tasklist detail
    function viewTasklistDetail(tasklistId) {
        if (!tasklistId || tasklistId === 'N/A') {
            alert('Tasklist ID tidak valid');
            return;
        }

        const modal = new bootstrap.Modal(document.getElementById('tasklistDetailModal'));
        const modalContent = document.getElementById('tasklistDetailContent');
        const modalTitle = document.getElementById('tasklistDetailModalLabel');

        // Show loading state
        modalContent.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Memuat detail tasklist...</p>
            </div>
        `;

        modal.show();

        // Fetch tasklist detail from API
        fetch(`{{ route('hazard-detection.api.tasklist-detail') }}?tasklist_id=${encodeURIComponent(tasklistId)}`)
            .then(response => response.json())
            .then(result => {
                if (!result.success || !result.data) {
                    modalContent.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="material-icons-outlined me-2">warning</i>
                            <div>
                                <p class="mb-0">${result.message || 'Gagal memuat detail tasklist'}</p>
                            </div>
                        </div>
                    `;
                    return;
                }

                const data = result.data;
                
                // Build title with badges
                const severityBadgeClass = data.severity === 'critical' ? 'bg-danger' : 
                                          data.severity === 'high' ? 'bg-warning' : 
                                          data.severity === 'medium' ? 'bg-info' : 'bg-secondary';
                const statusBadgeClass = data.status === 'active' ? 'bg-danger' : 'bg-success';
                
                modalTitle.innerHTML = `
                    <div class="d-flex align-items-center gap-2">
                        <i class="material-icons-outlined">assignment</i>
                        <span>Detail Tasklist ID: ${escapeHtml(tasklistId)}</span>
                        <span class="badge ${severityBadgeClass} ms-2">${escapeHtml(data.keparahan || data.severity || 'N/A')}</span>
                        <span class="badge ${statusBadgeClass}">${escapeHtml(data.status_name || data.status || 'N/A')}</span>
                    </div>
                `;

                // Build content HTML
                let contentHTML = `
                    <div class="row g-4">
                        <!-- Left Column - Basic Information -->
                        <div class="col-12 col-lg-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-gradient bg-primary text-white">
                                    <h6 class="mb-0 d-flex align-items-center text-white">
                                        <i class="material-icons-outlined me-2">info</i> Informasi Dasar
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-primary bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-primary">description</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Deskripsi</label>
                                                    <p class="mb-0">${escapeHtml(data.description || 'Tidak ada deskripsi')}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-info bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-info">category</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Ketidaksesuaian</label>
                                                    <p class="mb-0">${escapeHtml(data.ketidaksesuaian || data.type || 'N/A')}</p>
                                                </div>
                                            </div>
                                        </div>
                                        ${data.subketidaksesuaian ? `
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-secondary bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-secondary">label</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Sub Ketidaksesuaian</label>
                                                    <p class="mb-0">${escapeHtml(data.subketidaksesuaian)}</p>
                                                </div>
                                            </div>
                                        </div>
                                        ` : ''}
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-warning bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-warning">assessment</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Nilai Resiko</label>
                                                    <p class="mb-0">${escapeHtml(data.nilai_resiko || 'N/A')}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-danger bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-danger">trending_up</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Keparahan</label>
                                                    <p class="mb-0">${escapeHtml(data.keparahan || 'N/A')}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-success">schedule</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Kekerapan</label>
                                                    <p class="mb-0">${escapeHtml(data.kekerapan || 'N/A')}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column - Location & Personnel -->
                        <div class="col-12 col-lg-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-gradient bg-success text-white">
                                    <h6 class="mb-0 d-flex align-items-center text-white">
                                        <i class="material-icons-outlined me-2">location_on</i> Lokasi & Personil
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-success">place</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Site</label>
                                                    <p class="mb-0">${escapeHtml(data.site || 'N/A')}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-info bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-info">location_city</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Lokasi</label>
                                                    <p class="mb-0">${escapeHtml(data.nama_lokasi || data.zone || 'N/A')}</p>
                                                </div>
                                            </div>
                                        </div>
                                        ${data.nama_detail_lokasi ? `
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-primary bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-primary">pin_drop</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Detail Lokasi</label>
                                                    <p class="mb-0">${escapeHtml(data.nama_detail_lokasi)}</p>
                                                </div>
                                            </div>
                                        </div>
                                        ` : ''}
                                        ${data.lokasi_detail ? `
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-secondary bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-secondary">description</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Lokasi Detail</label>
                                                    <p class="mb-0">${escapeHtml(data.lokasi_detail)}</p>
                                                </div>
                                            </div>
                                        </div>
                                        ` : ''}
                                        ${data.nama_pelapor ? `
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-warning bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-warning">person</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Pelapor</label>
                                                    <p class="mb-0">${escapeHtml(data.nama_pelapor)}</p>
                                                </div>
                                            </div>
                                        </div>
                                        ` : ''}
                                        ${data.nama_pic ? `
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-danger bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-danger">person_pin</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">PIC</label>
                                                    <p class="mb-0">${escapeHtml(data.nama_pic)}</p>
                                                </div>
                                            </div>
                                        </div>
                                        ` : ''}
                                        ${data.cctv_id && data.cctv_id !== 'N/A' ? `
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-info bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-info">videocam</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">CCTV</label>
                                                    <p class="mb-0">${escapeHtml(data.cctv_id)}</p>
                                                </div>
                                            </div>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-gradient bg-info text-white">
                                    <h6 class="mb-0 d-flex align-items-center text-white">
                                        <i class="material-icons-outlined me-2">more_horiz</i> Informasi Tambahan
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        ${data.nama_goldenrule ? `
                                        <div class="col-12 col-md-6">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-warning bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-warning">rule</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Golden Rule</label>
                                                    <p class="mb-0">${escapeHtml(data.nama_goldenrule)}</p>
                                                </div>
                                            </div>
                                        </div>
                                        ` : ''}
                                        ${data.nama_kategori ? `
                                        <div class="col-12 col-md-6">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-primary bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-primary">category</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Kategori</label>
                                                    <p class="mb-0">${escapeHtml(data.nama_kategori)}</p>
                                                </div>
                                            </div>
                                        </div>
                                        ` : ''}
                                        <div class="col-12 col-md-6">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-success">schedule</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Tanggal Pembuatan</label>
                                                    <p class="mb-0">${escapeHtml(data.detected_at || data.tanggal_pembuatan || 'N/A')}</p>
                                                </div>
                                            </div>
                                        </div>
                                        ${data.resolved_at ? `
                                        <div class="col-12 col-md-6">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-danger bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-danger">check_circle</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Tanggal Penyelesaian</label>
                                                    <p class="mb-0">${escapeHtml(data.resolved_at)}</p>
                                                </div>
                                            </div>
                                        </div>
                                        ` : ''}
                                        ${data.url_photo ? `
                                        <div class="col-12">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-info bg-opacity-10 rounded-circle p-2">
                                                        <i class="material-icons-outlined text-info">image</i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-muted small mb-1">Foto</label>
                                                    <p class="mb-0">
                                                        <a href="${escapeHtml(data.url_photo)}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="material-icons-outlined me-1" style="font-size: 16px;">open_in_new</i>
                                                            Lihat Foto
                                                        </a>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                modalContent.innerHTML = contentHTML;
            })
            .catch(error => {
                console.error('Error fetching tasklist detail:', error);
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="material-icons-outlined me-2">error</i>
                        <div>
                            <p class="mb-0">Terjadi kesalahan saat memuat detail tasklist.</p>
                            <p class="mb-0 small text-muted mt-2">Error: ${escapeHtml(error.message || 'Unknown error')}</p>
                        </div>
                    </div>
                `;
            });
    }

    function refreshPythonApp() {
        const pythonAppFrame = document.getElementById('pythonAppFrame');
        const pythonAppLoading = document.getElementById('pythonAppLoading');
        const pythonAppError = document.getElementById('pythonAppError');
        
        if (!pythonAppFrame || !pythonAppLoading || !pythonAppError) {
            return;
        }

        // Show loading
        pythonAppLoading.style.display = 'block';
        pythonAppError.style.display = 'none';
        pythonAppFrame.style.display = 'none';

        // Reload iframe by appending timestamp to force refresh
        const currentSrc = pythonAppFrame.src.split('?')[0];
        pythonAppFrame.src = currentSrc + '?t=' + Date.now();

        // Check connection after reload
        setTimeout(function() {
            checkPythonAppConnection();
        }, 1000);
    }

    // Make refreshPythonApp globally accessible
    window.refreshPythonApp = refreshPythonApp;

    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Function to view CCTV incidents
    function viewCCTVIncidents(cctvName, cctvId, event) {
        if (event) {
            event.stopPropagation();
        }
        
        // Update modal title
        const modalTitle = document.getElementById('cctvIncidentsModalLabel');
        modalTitle.textContent = `Hazard Pelaporan - ${escapeHtml(cctvName)}`;
        
        const modalContent = document.getElementById('incidentsModalContent');
        
        // Show loading state
        modalContent.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Memuat data insiden dari database...</p>
            </div>
        `;
        
        // Show modal first
        const modal = new bootstrap.Modal(document.getElementById('cctvIncidentsModal'));
        modal.show();
        
        // Fetch incidents from database via API
        const apiUrl = '{{ route("hazard-detection.api.incidents-by-cctv") }}';
        const params = new URLSearchParams({
            cctv_name: cctvName || '',
            cctv_id: cctvId || ''
        });
        
        fetch(`${apiUrl}?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    const relatedHazards = data.data;
                    
                    // Add incidents to hazardDetections array if not already present
                    relatedHazards.forEach(function(incident) {
                        const existingIndex = hazardDetections.findIndex(h => h.id === incident.id);
                        if (existingIndex === -1) {
                            hazardDetections.push(incident);
                        } else {
                            // Update existing hazard with new data
                            hazardDetections[existingIndex] = incident;
                        }
                    });
                    
                    // Build table HTML
                    let tableHTML = `
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 fw-bold">Total Hazard Inspeksi: <span class="text-primary">${relatedHazards.length}</span></h6>
                                <p class="mb-0 text-muted small">CCTV: ${escapeHtml(cctvName)}</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Tasklist</th>
                                        <th>Type</th>
                                        <th>Severity</th>
                                        <th>Status</th>
                                        <th>Description</th>
                                        <th>Lokasi</th>
                                        <th>Detected At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    relatedHazards.forEach(function(hazard) {
                        const severityBadgeClass = hazard.severity === 'critical' ? 'bg-danger' : 
                                                  hazard.severity === 'high' ? 'bg-warning' : 'bg-info';
                        const statusBadgeClass = hazard.status === 'open' ? 'bg-danger' : 'bg-success';
                        
                        const description = escapeHtml(hazard.description || 'N/A');
                        const shortDescription = description.length > 100 ? description.substring(0, 100) + '...' : description;
                        
                        tableHTML += `
                            <tr>
                                <td><strong>#${escapeHtml(hazard.id)}</strong></td>
                                <td>${escapeHtml(hazard.type || 'N/A')}</td>
                                <td>
                                    <span class="badge ${severityBadgeClass}">${escapeHtml(hazard.keparahan || hazard.severity || 'N/A')}</span>
                                </td>
                                <td>
                                    <span class="badge ${statusBadgeClass}">${escapeHtml(hazard.status || 'N/A')}</span>
                                </td>
                                <td style="max-width: 300px;">
                                    <small class="text-muted" title="${description}">${shortDescription}</small>
                                </td>
                                <td>${escapeHtml(hazard.zone || 'Unknown')}</td>
                                <td><small>${escapeHtml(hazard.detected_at || hazard.tanggal_pembuatan || 'N/A')}</small></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewHazardDetails('${escapeHtml(hazard.id)}')">
                                        <i class="material-icons-outlined" style="font-size: 16px;">visibility</i>
                                        View
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    tableHTML += `
                                </tbody>
                            </table>
                        </div>
                    `;
                    
                    modalContent.innerHTML = tableHTML;
                } else {
                    // No incidents found
                    modalContent.innerHTML = `
                        <div class="alert alert-info text-center">
                            <i class="material-icons-outlined" style="font-size: 48px; color: #6b7280;">info</i>
                            <h6 class="mt-3">Tidak ada Hazard pelaporan ditemukan</h6>
                            <p class="mb-0 text-muted">Tidak ada Hazard yang terkait dengan CCTV: <strong>${escapeHtml(cctvName)}</strong></p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error fetching incidents:', error);
                modalContent.innerHTML = `
                    <div class="alert alert-danger text-center">
                        <i class="material-icons-outlined" style="font-size: 48px; color: #dc2626;">error</i>
                        <h6 class="mt-3">Error Memuat Data</h6>
                        <p class="mb-0 text-muted">Terjadi kesalahan saat memuat data insiden. Silakan coba lagi.</p>
                    </div>
                `;
            });
    }
    
    // Function to view PJA by CCTV
    function viewCCTVPja(cctvName, cctvId, event) {
        if (event) {
            event.stopPropagation();
        }
        
        // Update modal title
        const modalTitle = document.getElementById('cctvPjaModalLabel');
        modalTitle.textContent = `PJA & Laporan - ${escapeHtml(cctvName)}`;
        
        const modalContent = document.getElementById('pjaModalContent');
        
        // Show loading state
        modalContent.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Memuat data PJA dari database...</p>
            </div>
        `;
        
        // Show modal first
        const modal = new bootstrap.Modal(document.getElementById('cctvPjaModal'));
        modal.show();
        
        // Fetch PJA data from API
        const apiUrl = '{{ route("hazard-detection.api.pja-by-cctv") }}';
        const params = new URLSearchParams({
            cctv_name: cctvName || '',
            cctv_id: cctvId || ''
        });
        
        fetch(`${apiUrl}?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const pjaData = data.data;
                    const pjaList = pjaData.pja_list || [];
                    
                    if (pjaList.length === 0) {
                        modalContent.innerHTML = `
                            <div class="alert alert-info text-center">
                                <i class="material-icons-outlined" style="font-size: 48px; color: #6b7280;">info</i>
                                <h6 class="mt-3">Tidak ada PJA ditemukan</h6>
                                <p class="mb-0 text-muted">Tidak ada PJA yang terkait dengan lokasi CCTV: <strong>${escapeHtml(pjaData.cctv_info?.lokasi || cctvName)}</strong></p>
                            </div>
                        `;
                        return;
                    }
                    
                    // Build HTML content
                    let contentHTML = `
                        <div class="mb-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0 d-flex align-items-center">
                                        <i class="material-icons-outlined me-2">videocam</i>
                                        Informasi CCTV
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Nama CCTV</small>
                                            <strong>${escapeHtml(pjaData.cctv_info?.nama_cctv || cctvName)}</strong>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">No. CCTV</small>
                                            <strong>${escapeHtml(pjaData.cctv_info?.no_cctv || 'N/A')}</strong>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Lokasi</small>
                                            <strong>${escapeHtml(pjaData.cctv_info?.lokasi || 'N/A')}</strong>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Site</small>
                                            <strong>${escapeHtml(pjaData.cctv_info?.site || 'N/A')}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h5 class="mb-0">
                                <i class="material-icons-outlined me-2">construction</i>
                                Daftar PJA (${pjaList.length})
                            </h5>
                            <p class="text-muted small mb-0">Pekerjaan Jalan Angkut di lokasi ini</p>
                        </div>
                    `;
                    
                    // Loop through each PJA
                    pjaList.forEach(function(pjaItem, index) {
                        const pja = pjaItem.pja || 'N/A';
                        const namaPjaPerson = pjaItem.nama_pja_person || 'N/A';
                        const insidenCount = pjaItem.insiden_count || 0;
                        const hazardCount = pjaItem.hazard_count || 0;
                        const insidenList = pjaItem.insiden || [];
                        const hazardList = pjaItem.hazards || [];
                        
                        contentHTML += `
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-primary text-white">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <i class="material-icons-outlined" style="font-size: 24px;">construction</i>
                                                <div>
                                                    <small class="d-block opacity-75" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Pekerjaan Jalan Angkut</small>
                                                    <h5 class="mb-0 fw-bold">${escapeHtml(pja)}</h5>
                                                    ${namaPjaPerson && namaPjaPerson !== 'N/A' ? `
                                                        <div class="d-flex align-items-center gap-1 mt-1">
                                                            <i class="material-icons-outlined" style="font-size: 16px; opacity: 0.9;">person</i>
                                                            <span style="font-size: 13px; opacity: 0.9;">${escapeHtml(namaPjaPerson)}</span>
                                                        </div>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <span class="badge bg-light text-dark">
                                                <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">report_problem</i>
                                                ${insidenCount} Insiden
                                            </span>
                                            <span class="badge bg-light text-dark">
                                                <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">warning</i>
                                                ${hazardCount} Hazard
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info mb-3">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="material-icons-outlined mt-1">info</i>
                                            <div class="flex-grow-1">
                                                <div class="mb-2">
                                                    <strong>Nama PJA:</strong> ${escapeHtml(pja)}
                                                </div>
                                                ${namaPjaPerson && namaPjaPerson !== 'N/A' ? `
                                                    <div class="mb-2">
                                                        <strong>Nama Orang PJA:</strong> 
                                                        <span class="badge bg-primary bg-opacity-10 text-primary">
                                                            <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">person</i>
                                                            ${escapeHtml(namaPjaPerson)}
                                                        </span>
                                                    </div>
                                                ` : ''}
                                                <div>
                                                    <small>Total laporan: ${insidenCount + hazardCount} (${insidenCount} Insiden, ${hazardCount} Hazard)</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Insiden Section -->
                                    ${insidenCount > 0 ? `
                                        <div class="mb-4">
                                            <h6 class="mb-3 d-flex align-items-center">
                                                <i class="material-icons-outlined me-2 text-danger">report_problem</i>
                                                Insiden (${insidenCount})
                                            </h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>No. Kecelakaan</th>
                                                            <th>Tanggal</th>
                                                            <th>PJA</th>
                                                            <th>Nama Orang PJA</th>
                                                            <th>Lokasi</th>
                                                            <th>Kategori</th>
                                                            <th>Status LPI</th>
                                                            <th>High Potential</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ${insidenList.map(function(insiden) {
                                                            return `
                                                                <tr>
                                                                    <td><strong>${escapeHtml(insiden.no_kecelakaan || 'N/A')}</strong></td>
                                                                    <td>${escapeHtml(insiden.tanggal || 'N/A')}</td>
                                                                    <td>
                                                                        <span class="badge bg-info bg-opacity-10 text-info">
                                                                            <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">construction</i>
                                                                            ${escapeHtml(pja)}
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        ${insiden.nama ? `
                                                                            <div>
                                                                                <strong>${escapeHtml(insiden.nama)}</strong>
                                                                                ${insiden.jabatan ? `<br><small class="text-muted">${escapeHtml(insiden.jabatan)}</small>` : ''}
                                                                            </div>
                                                                        ` : insiden.atasan_langsung ? `
                                                                            <div>
                                                                                <strong>${escapeHtml(insiden.atasan_langsung)}</strong>
                                                                                ${insiden.jabatan_atasan_langsung ? `<br><small class="text-muted">${escapeHtml(insiden.jabatan_atasan_langsung)}</small>` : ''}
                                                                            </div>
                                                                        ` : '<span class="text-muted">-</span>'}
                                                                    </td>
                                                                    <td>
                                                                        <small>${escapeHtml(insiden.lokasi || 'N/A')}</small>
                                                                        ${insiden.sublokasi ? `<br><small class="text-muted">${escapeHtml(insiden.sublokasi)}</small>` : ''}
                                                                    </td>
                                                                    <td>${escapeHtml(insiden.kategori || 'N/A')}</td>
                                                                    <td>
                                                                        <span class="badge ${insiden.status_lpi === 'Closed' ? 'bg-success' : 'bg-warning'}">
                                                                            ${escapeHtml(insiden.status_lpi || 'N/A')}
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        ${insiden.high_potential ? 
                                                                            `<span class="badge bg-danger">${escapeHtml(insiden.high_potential)}</span>` : 
                                                                            '<span class="text-muted">-</span>'
                                                                        }
                                                                    </td>
                                                                </tr>
                                                            `;
                                                        }).join('')}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    ` : `
                                        <div class="alert alert-light text-center mb-4">
                                            <i class="material-icons-outlined text-muted">info</i>
                                            <p class="mb-0 text-muted">Tidak ada insiden untuk PJA ini</p>
                                        </div>
                                    `}
                                    
                                    <!-- Hazard Section -->
                                    ${hazardCount > 0 ? `
                                        <div>
                                            <h6 class="mb-3 d-flex align-items-center">
                                                <i class="material-icons-outlined me-2 text-warning">warning</i>
                                                Hazard (${hazardCount})
                                            </h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Type</th>
                                                            <th>PJA</th>
                                                            <th>Keparahan</th>
                                                            <th>Status</th>
                                                            <th>Deskripsi</th>
                                                            <th>Tanggal</th>
                                                            <th>Pelapor</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ${hazardList.map(function(hazard) {
                                                            const severityBadgeClass = hazard.severity === 'critical' ? 'bg-danger' : 
                                                                                      hazard.severity === 'high' ? 'bg-warning' : 
                                                                                      hazard.severity === 'medium' ? 'bg-info' : 'bg-secondary';
                                                            const statusBadgeClass = hazard.status === 'active' ? 'bg-danger' : 'bg-success';
                                                            const description = escapeHtml(hazard.description || 'N/A');
                                                            const shortDescription = description.length > 80 ? description.substring(0, 80) + '...' : description;
                                                            
                                                            return `
                                                                <tr>
                                                                    <td><strong>${escapeHtml(hazard.id || 'N/A')}</strong></td>
                                                                    <td>${escapeHtml(hazard.type || 'N/A')}</td>
                                                                    <td>
                                                                        <span class="badge bg-info bg-opacity-10 text-info">
                                                                            <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">construction</i>
                                                                            ${escapeHtml(pja)}
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge ${severityBadgeClass}">${escapeHtml(hazard.keparahan || 'N/A')}</span>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge ${statusBadgeClass}">${escapeHtml(hazard.status || 'N/A')}</span>
                                                                    </td>
                                                                    <td>
                                                                        <small title="${description}">${shortDescription}</small>
                                                                    </td>
                                                                    <td><small>${escapeHtml(hazard.tanggal_pembuatan || 'N/A')}</small></td>
                                                                    <td>${escapeHtml(hazard.nama_pelapor || 'N/A')}</td>
                                                                </tr>
                                                            `;
                                                        }).join('')}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    ` : `
                                        <div class="alert alert-light text-center">
                                            <i class="material-icons-outlined text-muted">info</i>
                                            <p class="mb-0 text-muted">Tidak ada hazard untuk PJA ini</p>
                                        </div>
                                    `}
                                </div>
                            </div>
                        `;
                    });
                    
                    modalContent.innerHTML = contentHTML;
                } else {
                    modalContent.innerHTML = `
                        <div class="alert alert-warning text-center">
                            <i class="material-icons-outlined" style="font-size: 48px; color: #f59e0b;">warning</i>
                            <h6 class="mt-3">Data tidak ditemukan</h6>
                            <p class="mb-0 text-muted">${data.message || 'Tidak dapat memuat data PJA'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error fetching PJA:', error);
                modalContent.innerHTML = `
                    <div class="alert alert-danger text-center">
                        <i class="material-icons-outlined" style="font-size: 48px; color: #dc2626;">error</i>
                        <h6 class="mt-3">Error Memuat Data</h6>
                        <p class="mb-0 text-muted">Terjadi kesalahan saat memuat data PJA. Silakan coba lagi.</p>
                    </div>
                `;
            });
    }
    
    // Function to reset hazard filter
    function resetHazardFilter() {
        const hazardItems = document.querySelectorAll('.hazard-item');
        hazardItems.forEach(function(item) {
            item.style.display = 'block';
        });
    }

    // Function to render CCTV streams
    function renderCCTVStreams() {
        const container = document.getElementById('cctvStreamContainer');
        if (!container || !cctvLocations || cctvLocations.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-4"><p class="mb-0">Tidak ada CCTV stream tersedia</p></div>';
            return;
        }
        
        container.innerHTML = '';
        
        cctvLocations.forEach(function(cctv, index) {
            const cctvItem = document.createElement('div');
            cctvItem.className = 'cctv-item border rounded-4 p-3';
            cctvItem.style.cursor = 'pointer';
            cctvItem.style.transition = 'all 0.2s';
            
            // Hover effect
            cctvItem.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f9fafb';
                this.style.borderColor = '#3b82f6';
            });
            cctvItem.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
                this.style.borderColor = '';
            });
            
            const rawRtspUrl = (cctv.rtsp_url && cctv.rtsp_url.trim() !== '') ? cctv.rtsp_url.trim() : '';
            const effectiveRtspUrl = rawRtspUrl || defaultCctvRtspUrl || '';
            const hasStream = effectiveRtspUrl !== '';
            const cctvName = cctv.name || cctv.cctv_name || cctv.nama_cctv || 'CCTV ' + (index + 1);
            const cctvSite = cctv.site || '';
            const cctvStatus = cctv.status || '';
            const linkAkses = cctv.link_akses || cctv.externalUrl || '';
            const perusahaan = cctv.perusahaan || cctv.perusahaan_cctv || 'N/A';
            
            // Click to open CCTV detail modal
            cctvItem.addEventListener('click', function(e) {
                // Jangan trigger jika klik pada button
                if (e.target.closest('button') || e.target.closest('a')) {
                    return;
                }
                // Buka modal detail CCTV dengan perusahaan
                openCctvDetailModal(perusahaan !== 'N/A' ? perusahaan : null);
            });
            
            cctvItem.innerHTML = `
                <div class="d-flex align-items-start gap-3">
                    <div class="position-relative" style="width: 120px; height: 80px; flex-shrink: 0;">
                        <div class="d-flex align-items-center justify-content-center w-100 h-100 bg-dark rounded" style="background: #111;">
                            <div class="text-center text-white-50">
                                <i class="material-icons-outlined" style="font-size: 32px;">${hasStream ? 'play_circle' : 'videocam_off'}</i>
                                <p class="mb-0 small" style="font-size: 10px;">${hasStream ? 'RTSP Ready' : 'No Stream'}</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 fw-bold">${cctvName}</h6>
                        ${cctvSite ? `<p class="mb-1 text-muted small"><i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">location_on</i> ${cctvSite}</p>` : ''}
                        ${perusahaan && perusahaan !== 'N/A' ? `<p class="mb-1 text-muted small"><i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">business</i> ${perusahaan}</p>` : ''}
                        ${cctvStatus ? `
                            <span class="badge ${cctvStatus === 'Live View' ? 'bg-success' : 'bg-secondary'} mb-2">
                                ${cctvStatus}
                            </span>
                        ` : ''}
                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            ${hasStream ? `
                                <button type="button" class="btn btn-sm btn-primary btn-open-stream-list" 
                                    data-cctv-name="${cctvName.replace(/"/g, '&quot;')}" 
                                    data-rtsp-url="${effectiveRtspUrl.replace(/"/g, '&quot;')}">
                                    <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">videocam</i>
                                    Stream Video
                                </button>
                            ` : linkAkses ? `
                                <a href="${linkAkses}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                    <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">open_in_new</i>
                                    Buka Link
                                </a>
                            ` : ''}
                            <button type="button" class="btn btn-sm btn-outline-info btn-view-cctv-detail-card" 
                                data-perusahaan="${perusahaan !== 'N/A' ? perusahaan.replace(/"/g, '&quot;') : ''}">
                                <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">list</i>
                                Detail CCTV
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(cctvItem);
            
            // Add event listener for view incidents button
            const viewIncidentsBtn = cctvItem.querySelector('.btn-view-incidents');
            if (viewIncidentsBtn) {
                viewIncidentsBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const cctvName = this.getAttribute('data-cctv-name');
                    const cctvId = this.getAttribute('data-cctv-id');
                    viewCCTVIncidents(cctvName, cctvId, e);
                });
            }
            
            // Add event listener for stream video button in list
            const openStreamListBtn = cctvItem.querySelector('.btn-open-stream-list');
            if (openStreamListBtn) {
                openStreamListBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const cctvName = this.getAttribute('data-cctv-name');
                    const rtspValue = this.getAttribute('data-rtsp-url');
                    openCCTVStreamModal(cctvName, rtspValue);
                });
            }
            
            // Add event listener for view CCTV detail button in card
            const viewCctvDetailCardBtn = cctvItem.querySelector('.btn-view-cctv-detail-card');
            if (viewCctvDetailCardBtn) {
                viewCctvDetailCardBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const perusahaan = this.getAttribute('data-perusahaan');
                    openCctvDetailModal(perusahaan || null);
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        switchView('hazard');
        
        // Load area kritis overview data saat halaman pertama kali dimuat
        // Pastikan filter default ke semua perusahaan & site
        currentSelectedCompany = '__all__';
        currentSelectedSite = '__all__';
        
        // Load filter options untuk halaman utama dan header
        loadMainFilterOptions();
        loadHeaderFilterOptions();
        
        loadAreaKritisOverview();
        loadChartStats();
        updateTotalCctvCount();
        
        // Event listener untuk filter di header (dropdown button)
        const headerFilterCompanyBtn = document.getElementById('headerFilterCompanyBtn');
        const headerFilterSiteBtn = document.getElementById('headerFilterSiteBtn');
        const headerFilterCompanyText = document.getElementById('headerFilterCompanyText');
        const headerFilterSiteText = document.getElementById('headerFilterSiteText');
        const btnResetHeaderFilter = document.getElementById('btnResetHeaderFilter');
        
        // Event listener untuk dropdown company di header
        const headerFilterCompanyDropdown = document.getElementById('headerFilterCompanyDropdown');
        if (headerFilterCompanyDropdown) {
            headerFilterCompanyDropdown.addEventListener('click', function(e) {
                const target = e.target.closest('.filter-option');
                if (target) {
                    e.preventDefault();
                    const value = target.getAttribute('data-value');
                    const text = target.textContent.trim();
                    currentSelectedCompany = value || '__all__';
                    
                    // Update button text di header
                    if (headerFilterCompanyText) {
                        headerFilterCompanyText.textContent = text;
                    }
                    
                    // Update button text di map filter juga
                    const mainFilterCompanyText = document.getElementById('mainFilterCompanyText');
                    if (mainFilterCompanyText) {
                        mainFilterCompanyText.textContent = text;
                    }
                    
                    // Update filter di modal juga jika ada
                    const modalFilterCompany = document.getElementById('filterCompany');
                    if (modalFilterCompany) {
                        modalFilterCompany.value = currentSelectedCompany;
                    }
                    
                    // Close dropdown
                    const dropdown = bootstrap.Dropdown.getInstance(headerFilterCompanyBtn);
                    if (dropdown) {
                        dropdown.hide();
                    }
                    
                    // Update semua statistik berdasarkan filter
                    loadChartStats();
                    loadAreaKritisOverview();
                    updateTotalCctvCount();
                }
            });
        }
        
        // Event listener untuk dropdown site di header
        const headerFilterSiteDropdown = document.getElementById('headerFilterSiteDropdown');
        if (headerFilterSiteDropdown) {
            headerFilterSiteDropdown.addEventListener('click', function(e) {
                const target = e.target.closest('.filter-option');
                if (target) {
                    e.preventDefault();
                    const value = target.getAttribute('data-value');
                    const text = target.textContent.trim();
                    currentSelectedSite = value || '__all__';
                    
                    // Update button text di header
                    if (headerFilterSiteText) {
                        headerFilterSiteText.textContent = text;
                    }
                    
                    // Update button text di map filter juga
                    const mainFilterSiteText = document.getElementById('mainFilterSiteText');
                    if (mainFilterSiteText) {
                        mainFilterSiteText.textContent = text;
                    }
                    
                    // Update filter di modal juga jika ada
                    const modalFilterSite = document.getElementById('filterSite');
                    if (modalFilterSite) {
                        modalFilterSite.value = currentSelectedSite;
                    }
                    
                    // Update currentSiteFilter untuk filter map dan hazard list
                    currentSiteFilter = (currentSelectedSite !== '__all__') ? currentSelectedSite : '';
                    filterBySite(currentSiteFilter);
                    filterHazardListView(currentSiteFilter);
                    updateStatisticsBySite(currentSiteFilter);
                    
                    // Close dropdown
                    const dropdown = bootstrap.Dropdown.getInstance(headerFilterSiteBtn);
                    if (dropdown) {
                        dropdown.hide();
                    }
                    
                    // Update semua statistik berdasarkan filter
                    loadChartStats();
                    loadAreaKritisOverview();
                    updateTotalCctvCount();
                    updateTotalCctvCount();
                }
            });
        }
        
        if (btnResetHeaderFilter) {
            btnResetHeaderFilter.addEventListener('click', function() {
                currentSelectedCompany = '__all__';
                currentSelectedSite = '__all__';
                
                // Update button text di header
                if (headerFilterCompanyText) {
                    headerFilterCompanyText.textContent = 'Semua Perusahaan';
                }
                if (headerFilterSiteText) {
                    headerFilterSiteText.textContent = 'Semua Site';
                }
                
                // Update button text di map filter juga
                const mainFilterCompanyText = document.getElementById('mainFilterCompanyText');
                const mainFilterSiteText = document.getElementById('mainFilterSiteText');
                if (mainFilterCompanyText) {
                    mainFilterCompanyText.textContent = 'Semua Perusahaan';
                }
                if (mainFilterSiteText) {
                    mainFilterSiteText.textContent = 'Semua Site';
                }
                
                // Update currentSiteFilter untuk filter map dan hazard list
                currentSiteFilter = '';
                filterBySite(currentSiteFilter);
                filterHazardListView(currentSiteFilter);
                updateStatisticsBySite(currentSiteFilter);
                
                // Update filter di modal juga
                const modalFilterCompany = document.getElementById('filterCompany');
                const modalFilterSite = document.getElementById('filterSite');
                if (modalFilterCompany) {
                    modalFilterCompany.value = '__all__';
                }
                if (modalFilterSite) {
                    modalFilterSite.value = '__all__';
                }
                
                // Update semua statistik
                loadChartStats();
                loadAreaKritisOverview();
                updateTotalCctvCount();
            });
        }
        
        // Event listener untuk filter di halaman utama (dropdown button) - di map card
        const mainFilterCompanyBtn = document.getElementById('mainFilterCompanyBtn');
        const mainFilterSiteBtn = document.getElementById('mainFilterSiteBtn');
        const mainFilterCompanyText = document.getElementById('mainFilterCompanyText');
        const mainFilterSiteText = document.getElementById('mainFilterSiteText');
        const btnResetMainFilter = document.getElementById('btnResetMainFilter');
        
        // Event listener untuk dropdown company
        const mainFilterCompanyDropdown = document.getElementById('mainFilterCompanyDropdown');
        if (mainFilterCompanyDropdown) {
            mainFilterCompanyDropdown.addEventListener('click', function(e) {
                const target = e.target.closest('.filter-option');
                if (target) {
                    e.preventDefault();
                    const value = target.getAttribute('data-value');
                    const text = target.textContent.trim();
                    currentSelectedCompany = value || '__all__';
                    
                    // Update button text di map filter
                    if (mainFilterCompanyText) {
                        mainFilterCompanyText.textContent = text;
                    }
                    
                    // Update button text di header filter juga
                    const headerFilterCompanyText = document.getElementById('headerFilterCompanyText');
                    if (headerFilterCompanyText) {
                        headerFilterCompanyText.textContent = text;
                    }
                    
                    // Update filter di modal juga jika ada
                    const modalFilterCompany = document.getElementById('filterCompany');
                    if (modalFilterCompany) {
                        modalFilterCompany.value = currentSelectedCompany;
                    }
                    
                    // Close dropdown
                    const dropdown = bootstrap.Dropdown.getInstance(mainFilterCompanyBtn);
                    if (dropdown) {
                        dropdown.hide();
                    }
                    
                    // Update semua statistik berdasarkan filter
                    loadChartStats();
                    loadAreaKritisOverview();
                    updateTotalCctvCount();
                }
            });
        }
        
        // Event listener untuk dropdown site
        const mainFilterSiteDropdown = document.getElementById('mainFilterSiteDropdown');
        if (mainFilterSiteDropdown) {
            mainFilterSiteDropdown.addEventListener('click', function(e) {
                const target = e.target.closest('.filter-option');
                if (target) {
                    e.preventDefault();
                    const value = target.getAttribute('data-value');
                    const text = target.textContent.trim();
                    currentSelectedSite = value || '__all__';
                    
                    // Update button text di map filter
                    if (mainFilterSiteText) {
                        mainFilterSiteText.textContent = text;
                    }
                    
                    // Update button text di header filter juga
                    const headerFilterSiteText = document.getElementById('headerFilterSiteText');
                    if (headerFilterSiteText) {
                        headerFilterSiteText.textContent = text;
                    }
                    
                    // Update filter di modal juga jika ada
                    const modalFilterSite = document.getElementById('filterSite');
                    if (modalFilterSite) {
                        modalFilterSite.value = currentSelectedSite;
                    }
                    
                    // Update currentSiteFilter untuk filter map dan hazard list
                    currentSiteFilter = (currentSelectedSite !== '__all__') ? currentSelectedSite : '';
                    filterBySite(currentSiteFilter);
                    filterHazardListView(currentSiteFilter);
                    updateStatisticsBySite(currentSiteFilter);
                    
                    // Close dropdown
                    const dropdown = bootstrap.Dropdown.getInstance(mainFilterSiteBtn);
                    if (dropdown) {
                        dropdown.hide();
                    }
                    
                    // Update semua statistik berdasarkan filter
                    loadChartStats();
                    loadAreaKritisOverview();
                    updateTotalCctvCount();
                }
            });
        }
        
        if (btnResetMainFilter) {
            btnResetMainFilter.addEventListener('click', function() {
                currentSelectedCompany = '__all__';
                currentSelectedSite = '__all__';
                
                // Update button text di map filter
                if (mainFilterCompanyText) {
                    mainFilterCompanyText.textContent = 'Semua Perusahaan';
                }
                if (mainFilterSiteText) {
                    mainFilterSiteText.textContent = 'Semua Site';
                }
                
                // Update button text di header filter juga
                const headerFilterCompanyText = document.getElementById('headerFilterCompanyText');
                const headerFilterSiteText = document.getElementById('headerFilterSiteText');
                if (headerFilterCompanyText) {
                    headerFilterCompanyText.textContent = 'Semua Perusahaan';
                }
                if (headerFilterSiteText) {
                    headerFilterSiteText.textContent = 'Semua Site';
                }
                
                // Update currentSiteFilter untuk filter map dan hazard list
                currentSiteFilter = '';
                filterBySite(currentSiteFilter);
                filterHazardListView(currentSiteFilter);
                updateStatisticsBySite(currentSiteFilter);
                
                // Update filter di modal juga
                const modalFilterCompany = document.getElementById('filterCompany');
                const modalFilterSite = document.getElementById('filterSite');
                if (modalFilterCompany) {
                    modalFilterCompany.value = '__all__';
                }
                if (modalFilterSite) {
                    modalFilterSite.value = '__all__';
                }
                
                // Update semua statistik
                loadChartStats();
                loadAreaKritisOverview();
                updateTotalCctvCount();
            });
        }
        
        // Add click event listener to TBC stat card
        const cctvStatCard = document.getElementById('cctvStatCard');
        if (cctvStatCard) {
            cctvStatCard.addEventListener('click', function() {
                openTbcOverviewModal();
            });
            
            // Add hover effect
            cctvStatCard.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fa';
                this.style.transform = 'scale(1.02)';
            });
            
            cctvStatCard.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
                this.style.transform = 'scale(1)';
            });
        }
    });

    // DataTable untuk modal Total CCTV
    let companyCctvTable = null;
    let currentSelectedCompany = '__all__';
    let currentSelectedSite = '__all__';
    
    // Chart instances
    let chartSiteBar = null;
    let chartStatusPie = null;
    let chartCompanyBar = null;
    let chartKondisiPie = null;
    let chartKategoriCctvPie = null;
    let chartKategoriAreaPie = null;
    let chartKategoriAktivitasPie = null;
    let chartTipeCctvBar = null;
    let chartJenisInstalasiBar = null;
    let chartTimeSeries = null;
    
    // Load awal statistik chart (termasuk mini chart Top 9 perusahaan di card YTD Insiden)
    window.addEventListener('load', function () {
        // Pastikan filter default ke semua perusahaan & site
        currentSelectedCompany = '__all__';
        currentSelectedSite = '__all__';
        // Panggil API statistik CCTV
        loadChartStats();
    });
    
    // Function untuk membuka modal TBC Overview
    function openTbcOverviewModal() {
        const modal = new bootstrap.Modal(document.getElementById('tbcOverviewModal'));
        modal.show();
        
        // Load data saat modal dibuka
        loadTbcOverviewData();
    }
    
    // Function untuk load TBC overview data
    function loadTbcOverviewData() {
        // Show loading state
        const tbcDataTableBody = document.getElementById('tbcDataTableBody');
        if (tbcDataTableBody) {
            tbcDataTableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Memuat data...</td></tr>';
        }
        
        fetch(`{{ route('hazard-detection.api.tbc-overview') }}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update KPI cards
                    animateNumber('tbcTotalCount', data.total_tbc || 0, 800);
                    animateNumber('tbcThisYearCount', data.statistics?.this_year || 0, 800);
                    animateNumber('tbcLastYearCount', data.statistics?.last_year || 0, 800);
                    animateNumber('tbcWithDataCount', data.statistics?.with_postgres_data || 0, 800);
                    
                    // Update severity breakdown
                    if (data.statistics?.by_severity) {
                        animateNumber('tbcSeverityCritical', data.statistics.by_severity.critical || 0, 800);
                        animateNumber('tbcSeverityHigh', data.statistics.by_severity.high || 0, 800);
                        animateNumber('tbcSeverityMedium', data.statistics.by_severity.medium || 0, 800);
                        animateNumber('tbcSeverityLow', data.statistics.by_severity.low || 0, 800);
                    }
                    
                    // Update charts
                    updateTbcCompanyChart(data.by_company || []);
                    updateTbcStatusChart(data.by_status || []);
                    
                    // Update data table
                    updateTbcDataTable(data.data || []);
                } else {
                    console.error('Error loading TBC overview:', data.message);
                    if (tbcDataTableBody) {
                        tbcDataTableBody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Error: ' + (data.message || 'Gagal memuat data') + '</td></tr>';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading TBC overview:', error);
                if (tbcDataTableBody) {
                    tbcDataTableBody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Error: Gagal memuat data</td></tr>';
                }
            });
    }
    
    // Chart instances untuk TBC
    let tbcCompanyChart = null;
    let tbcStatusChart = null;
    
    // Function untuk update TBC Company Chart
    function updateTbcCompanyChart(data) {
        const ctx = document.getElementById('tbcCompanyChart');
        if (!ctx) return;
        
        const labels = data.map(item => item.company || 'Tidak Diketahui');
        const values = data.map(item => item.count || 0);
        
        if (tbcCompanyChart) {
            tbcCompanyChart.destroy();
        }
        
        tbcCompanyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah TBC',
                    data: values,
                    backgroundColor: 'rgba(111, 66, 193, 0.8)',
                    borderColor: 'rgba(111, 66, 193, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
    
    // Function untuk update TBC Status Chart
    function updateTbcStatusChart(data) {
        const ctx = document.getElementById('tbcStatusChart');
        if (!ctx) return;
        
        const labels = data.map(item => item.status || 'Unknown');
        const values = data.map(item => item.count || 0);
        
        const colors = [
            'rgba(255, 99, 132, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 206, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
        ];
        
        if (tbcStatusChart) {
            tbcStatusChart.destroy();
        }
        
        tbcStatusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah TBC',
                    data: values,
                    backgroundColor: colors.slice(0, labels.length),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Function untuk update TBC Data Table
    function updateTbcDataTable(data) {
        const tbcDataTableBody = document.getElementById('tbcDataTableBody');
        if (!tbcDataTableBody) return;
        
        if (data.length === 0) {
            tbcDataTableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data</td></tr>';
            return;
        }
        
        let html = '';
        data.forEach((item, index) => {
            const severityBadge = {
                'critical': '<span class="badge bg-danger">Sangat Tinggi</span>',
                'high': '<span class="badge bg-warning">Tinggi</span>',
                'medium': '<span class="badge bg-info">Sedang</span>',
                'low': '<span class="badge bg-success">Rendah</span>'
            }[item.severity] || '<span class="badge bg-secondary">-</span>';
            
            const statusBadge = item.status === 'active' 
                ? '<span class="badge bg-danger">' + (item.status_name || 'Open') + '</span>'
                : '<span class="badge bg-success">' + (item.status_name || 'Closed') + '</span>';
            
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td><code>${item.tasklist || '-'}</code></td>
                    <td>${(item.deskripsi || '-').substring(0, 50)}${(item.deskripsi || '').length > 50 ? '...' : ''}</td>
                    <td>${item.nama_detail_lokasi || item.lokasi_detail || '-'}</td>
                    <td>${item.nama_site || '-'}</td>
                    <td>${severityBadge}</td>
                    <td>${statusBadge}</td>
                    <td>${item.nama_pelapor || '-'}</td>
                    <td>${item.tanggal_pembuatan ? new Date(item.tanggal_pembuatan).toLocaleDateString('id-ID') : '-'}</td>
                </tr>
            `;
        });
        
        tbcDataTableBody.innerHTML = html;
    }
    
    // Function untuk membuka modal detail CCTV
    function openCctvDetailModal(perusahaan = null) {
        const modal = new bootstrap.Modal(document.getElementById('totalCctvModal'));
        
        // Set filter values if perusahaan provided, otherwise use current filter values
        if (perusahaan && perusahaan !== 'N/A') {
            currentSelectedCompany = perusahaan;
        }
        // Jika tidak ada perusahaan yang dipilih, gunakan filter yang sudah dipilih di halaman utama
        // currentSelectedCompany dan currentSelectedSite sudah memiliki nilai dari filter halaman utama
        
        modal.show();
        
        // Wait for modal to be shown, then load data
        const modalElement = document.getElementById('totalCctvModal');
        const onModalShown = function() {
            // Set filter dropdowns dengan nilai dari filter halaman utama
            const filterCompanyEl = document.getElementById('filterCompany');
            const filterSiteEl = document.getElementById('filterSite');
            
            if (filterCompanyEl) {
                filterCompanyEl.value = currentSelectedCompany || '__all__';
            }
            if (filterSiteEl) {
                filterSiteEl.value = currentSelectedSite || '__all__';
            }
            
            // Load filter options first, then load data
            loadFilterOptions();
            
            // Update label
            updateFilterLabel();
            
            // Load initial data after a short delay to ensure modal is fully rendered
            setTimeout(() => {
                loadChartStats();
                loadAreaKritisOverview();
                updateTotalCctvCount();
            }, 500);
        };
        
        // Add event listener (will be removed after first use)
        modalElement.addEventListener('shown.bs.modal', onModalShown, { once: true });
    }
    
    // Function untuk load filter options untuk modal
    function loadFilterOptions() {
        // Load companies
        fetch('{{ route("hazard-detection.api.company-overview") }}')
            .then(response => response.json())
            .then(data => {
                const companySelect = document.getElementById('filterCompany');
                if (companySelect) {
                    companySelect.innerHTML = '<option value="__all__">Semua Perusahaan</option>';
                    
                    if (data.success && data.data.length > 0) {
                        data.data.forEach(company => {
                            const option = document.createElement('option');
                            option.value = company.perusahaan;
                            option.textContent = company.perusahaan;
                            companySelect.appendChild(option);
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error loading companies:', error);
            });
        
        // Load sites
        fetch('{{ route("hazard-detection.api.sites-list") }}')
            .then(response => response.json())
            .then(data => {
                const siteSelect = document.getElementById('filterSite');
                if (siteSelect) {
                    siteSelect.innerHTML = '<option value="__all__">Semua Site</option>';
                    
                    if (data.success && data.data.length > 0) {
                        data.data.forEach(site => {
                            const option = document.createElement('option');
                            option.value = site;
                            option.textContent = site;
                            siteSelect.appendChild(option);
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error loading sites:', error);
            });
    }
    
    // Function untuk load filter options untuk halaman utama (dropdown button) - di map card
    function loadMainFilterOptions() {
        // Load companies
        fetch('{{ route("hazard-detection.api.company-overview") }}')
            .then(response => response.json())
            .then(data => {
                const companyDropdown = document.getElementById('mainFilterCompanyDropdown');
                if (companyDropdown) {
                    companyDropdown.innerHTML = '<li><a class="dropdown-item filter-option" href="javascript:;" data-value="__all__">Semua Perusahaan</a></li>';
                    
                    if (data.success && data.data.length > 0) {
                        data.data.forEach(company => {
                            const li = document.createElement('li');
                            const a = document.createElement('a');
                            a.className = 'dropdown-item filter-option';
                            a.href = 'javascript:;';
                            a.setAttribute('data-value', company.perusahaan);
                            a.textContent = company.perusahaan;
                            li.appendChild(a);
                            companyDropdown.appendChild(li);
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error loading companies:', error);
            });
        
        // Load sites
        fetch('{{ route("hazard-detection.api.sites-list") }}')
            .then(response => response.json())
            .then(data => {
                const siteDropdown = document.getElementById('mainFilterSiteDropdown');
                if (siteDropdown) {
                    siteDropdown.innerHTML = '<li><a class="dropdown-item filter-option" href="javascript:;" data-value="__all__">Semua Site</a></li>';
                    
                    if (data.success && data.data.length > 0) {
                        data.data.forEach(site => {
                            const li = document.createElement('li');
                            const a = document.createElement('a');
                            a.className = 'dropdown-item filter-option';
                            a.href = 'javascript:;';
                            a.setAttribute('data-value', site);
                            a.textContent = site;
                            li.appendChild(a);
                            siteDropdown.appendChild(li);
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error loading sites:', error);
            });
    }
    
    // Function untuk load filter options untuk header (dropdown button)
    function loadHeaderFilterOptions() {
        // Load companies
        fetch('{{ route("hazard-detection.api.company-overview") }}')
            .then(response => response.json())
            .then(data => {
                const companyDropdown = document.getElementById('headerFilterCompanyDropdown');
                if (companyDropdown) {
                    companyDropdown.innerHTML = '<li><a class="dropdown-item filter-option" href="javascript:;" data-value="__all__">Semua Perusahaan</a></li>';
                    
                    if (data.success && data.data.length > 0) {
                        data.data.forEach(company => {
                            const li = document.createElement('li');
                            const a = document.createElement('a');
                            a.className = 'dropdown-item filter-option';
                            a.href = 'javascript:;';
                            a.setAttribute('data-value', company.perusahaan);
                            a.textContent = company.perusahaan;
                            li.appendChild(a);
                            companyDropdown.appendChild(li);
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error loading companies:', error);
            });
        
        // Load sites
        fetch('{{ route("hazard-detection.api.sites-list") }}')
            .then(response => response.json())
            .then(data => {
                const siteDropdown = document.getElementById('headerFilterSiteDropdown');
                if (siteDropdown) {
                    siteDropdown.innerHTML = '<li><a class="dropdown-item filter-option" href="javascript:;" data-value="__all__">Semua Site</a></li>';
                    
                    if (data.success && data.data.length > 0) {
                        data.data.forEach(site => {
                            const li = document.createElement('li');
                            const a = document.createElement('a');
                            a.className = 'dropdown-item filter-option';
                            a.href = 'javascript:;';
                            a.setAttribute('data-value', site);
                            a.textContent = site;
                            li.appendChild(a);
                            siteDropdown.appendChild(li);
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error loading sites:', error);
            });
    }
    
    // Function untuk update label berdasarkan filter
    function updateFilterLabel() {
        const companyLabel = currentSelectedCompany === '__all__' ? 'Semua Perusahaan' : currentSelectedCompany;
        const siteLabel = currentSelectedSite === '__all__' ? 'Semua Site' : currentSelectedSite;
        const labelElement = document.getElementById('companyCctvCompanyLabel');
        if (labelElement) {
            labelElement.textContent = `${companyLabel} - ${siteLabel}`;
        }
    }
    
    // Function untuk load area kritis overview (untuk section di halaman utama)
    function loadAreaKritisOverview() {
        const company = currentSelectedCompany || '__all__';
        const site = currentSelectedSite || '__all__';
        
        fetch(`{{ route('hazard-detection.api.cctv-chart-stats') }}?company=${encodeURIComponent(company)}&site=${encodeURIComponent(site)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update Area Kritis Overview dengan animasi
                    animateNumber('modalJumlahAreaKritis', data.jumlahAreaKritis || 0, 800);
                    animateNumber('modalCctvAreaKritis', data.cctvAreaKritis || 0, 800);
                    animateNumber('modalCctvAreaNonKritis', data.cctvAreaNonKritis || 0, 800);
                    
                    // Update detail data di accordion body
                    const totalArea = (data.jumlahAreaKritis || 0) + (data.jumlahAreaNonKritis || 0);
                    const totalCctv = data.total || 0;
                    
                    // Update Jumlah Area Kritis detail
                    animateNumber('detailJumlahAreaKritis', data.jumlahAreaKritis || 0, 800);
                    animateNumber('detailJumlahAreaNonKritis', data.jumlahAreaNonKritis || 0, 800);
                    animateNumber('detailTotalArea', totalArea, 800);
                    
                    // Update list detail area kritis
                    updateDetailAreaKritisList(data.detailAreaKritis || []);
                    
                    // Update CCTV Area Kritis detail
                    animateNumber('detailCctvAreaKritis', data.cctvAreaKritis || 0, 800);
                    animateNumber('detailTotalCctvAreaKritis', totalCctv, 800);
                    const persentaseCctvAreaKritis = totalCctv > 0 ? ((data.cctvAreaKritis || 0) / totalCctv * 100).toFixed(1) : 0;
                    const persentaseCctvAreaKritisEl = document.getElementById('detailPersentaseCctvAreaKritis');
                    if (persentaseCctvAreaKritisEl) {
                        // Animate percentage dengan delay untuk efek yang lebih baik
                        setTimeout(() => {
                            persentaseCctvAreaKritisEl.textContent = `${persentaseCctvAreaKritis}%`;
                            persentaseCctvAreaKritisEl.style.transform = 'scale(1.1)';
                            setTimeout(() => {
                                persentaseCctvAreaKritisEl.style.transform = 'scale(1)';
                            }, 200);
                        }, 400);
                    }
                    
                    // Update CCTV Area Non Kritis detail
                    animateNumber('detailCctvAreaNonKritis', data.cctvAreaNonKritis || 0, 800);
                    animateNumber('detailTotalCctvAreaNonKritis', totalCctv, 800);
                    const persentaseCctvAreaNonKritis = totalCctv > 0 ? ((data.cctvAreaNonKritis || 0) / totalCctv * 100).toFixed(1) : 0;
                    const persentaseCctvAreaNonKritisEl = document.getElementById('detailPersentaseCctvAreaNonKritis');
                    if (persentaseCctvAreaNonKritisEl) {
                        // Animate percentage dengan delay untuk efek yang lebih baik
                        setTimeout(() => {
                            persentaseCctvAreaNonKritisEl.textContent = `${persentaseCctvAreaNonKritis}%`;
                            persentaseCctvAreaNonKritisEl.style.transform = 'scale(1.1)';
                            setTimeout(() => {
                                persentaseCctvAreaNonKritisEl.style.transform = 'scale(1)';
                            }, 200);
                        }, 400);
                    }
                }
            })
            .catch(error => {
                console.error('Error loading area kritis overview:', error);
            });
    }
    
    // Function untuk update list detail area kritis
    function updateDetailAreaKritisList(detailAreaKritis) {
        const listContainer = document.getElementById('listDetailAreaKritis');
        if (!listContainer) return;
        
        if (!detailAreaKritis || detailAreaKritis.length === 0) {
            listContainer.innerHTML = `
                <strong>Detail Area Kritis:</strong>
                <div class="mt-2">
                    <small class="text-muted">Tidak ada data area kritis</small>
                </div>
            `;
            return;
        }
        
        let html = '<strong>Detail Area Kritis:</strong><div class="mt-2">';
        
        // Tampilkan maksimal 10 area pertama
        const maxItems = Math.min(detailAreaKritis.length, 10);
        for (let i = 0; i < maxItems; i++) {
            const area = detailAreaKritis[i];
            html += `
                <div class="d-flex align-items-center justify-content-between p-2 mb-2 bg-white border rounded">
                    <span class="text-truncate me-2" style="max-width: 60%;" title="${area.nama_area || 'N/A'}">
                        <i class="material-icons-outlined text-danger me-1" style="font-size: 16px;">location_on</i>
                        ${area.nama_area || 'N/A'}
                    </span>
                    <span class="badge bg-danger">${area.jumlah_cctv || 0} CCTV</span>
                </div>
            `;
        }
        
        if (detailAreaKritis.length > 10) {
            html += `<small class="text-muted">dan ${detailAreaKritis.length - 10} area lainnya...</small>`;
        }
        
        html += '</div>';
        listContainer.innerHTML = html;
    }
    
    // Function untuk load chart statistics
    function loadChartStats() {
        const company = currentSelectedCompany;
        const site = currentSelectedSite;
        
        fetch(`{{ route('hazard-detection.api.cctv-chart-stats') }}?company=${encodeURIComponent(company)}&site=${encodeURIComponent(site)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update KPI Summary Cards with animation
                    animateNumber('modalTotalCctv', data.total || 0, 800);
                    animateNumber('modalCctvAktif', data.cctvAktif || 0, 800);
                    animateNumber('modalCctvKondisiBaik', data.cctvKondisiBaik || 0, 800);
                    animateNumber('modalCctvAutoAlert', data.cctvAutoAlert || 0, 800);
                    animateNumber('modalCctvKondisiTidakBaik', data.cctvKondisiTidakBaik || 0, 800);

                    // Update ringkasan CCTV di kartu utama (CCTV Aktif, Kondisi Baik, Kondisi Tidak Baik)
                    const totalCctvMain = data.total || 0;

                    // CCTV Aktif
                    const cctvAktifCount = data.cctvAktif || 0;
                    const cctvAktifPercentage = totalCctvMain > 0 ? ((cctvAktifCount / totalCctvMain) * 100).toFixed(1) : 0;
                    animateNumber('statCctvAktifCount', cctvAktifCount, 800);
                    const statCctvAktifChange = document.getElementById('statCctvAktifChange');
                    const statCctvAktifText = document.getElementById('statCctvAktifText');
                    if (statCctvAktifChange) {
                        statCctvAktifChange.textContent = `${cctvAktifPercentage}%`;
                    }
                    if (statCctvAktifText) {
                        statCctvAktifText.textContent = `${cctvAktifCount.toLocaleString('id-ID')} CCTV`;
                    }
                    updateDonutChart('donutCctvAktif', cctvAktifPercentage, '#6f42c1');

                    // CCTV Kondisi Baik
                    const kondisiBaikCount = data.cctvKondisiBaik || 0;
                    const kondisiBaikPercentage = totalCctvMain > 0 ? ((kondisiBaikCount / totalCctvMain) * 100).toFixed(1) : 0;
                    animateNumber('statKondisiBaikCount', kondisiBaikCount, 800);
                    const statKondisiBaikChange = document.getElementById('statKondisiBaikChange');
                    const statKondisiBaikText = document.getElementById('statKondisiBaikText');
                    if (statKondisiBaikChange) {
                        statKondisiBaikChange.textContent = `${kondisiBaikPercentage}%`;
                    }
                    if (statKondisiBaikText) {
                        statKondisiBaikText.textContent = `${kondisiBaikCount.toLocaleString('id-ID')} CCTV`;
                    }
                    updateDonutChart('donutKondisiBaik', kondisiBaikPercentage, '#0d6efd');

                    // CCTV Kondisi Tidak Baik
                    const kondisiTidakBaikCount = data.cctvKondisiTidakBaik || 0;
                    const kondisiTidakBaikPercentage = totalCctvMain > 0 ? ((kondisiTidakBaikCount / totalCctvMain) * 100).toFixed(1) : 0;
                    animateNumber('statKondisiTidakBaikCount', kondisiTidakBaikCount, 800);
                    const statKondisiTidakBaikChange = document.getElementById('statKondisiTidakBaikChange');
                    const statKondisiTidakBaikText = document.getElementById('statKondisiTidakBaikText');
                    if (statKondisiTidakBaikChange) {
                        statKondisiTidakBaikChange.textContent = `${kondisiTidakBaikPercentage}%`;
                    }
                    if (statKondisiTidakBaikText) {
                        statKondisiTidakBaikText.textContent = `${kondisiTidakBaikCount.toLocaleString('id-ID')} CCTV`;
                    }
                    updateDonutChart('donutKondisiTidakBaik', kondisiTidakBaikPercentage, '#fd7e14');
                    
                    // Update Coverage Badge
                    const totalCctv = data.total || 0;
                    const coveragePercentage = totalCctv > 0 ? ((data.cctvAktif || 0) / totalCctv * 100).toFixed(1) : 0;
                    const coverageBadge = document.getElementById('modalCoverageBadge');
                    if (coverageBadge) {
                        coverageBadge.textContent = `${coveragePercentage}% Coverage`;
                    }

                    const criticalAreaCardCountEl = document.getElementById('criticalAreaCardCount');
                    if (criticalAreaCardCountEl) {
                        animateNumber('criticalAreaCardCount', data.cctvAreaKritis || 0, 800);
                    }
                    const criticalCoverageDescription = document.getElementById('criticalCoverageDescription');
                    const fallbackCriticalCoveragePercent = window.initialCriticalCoveragePercentage ?? 95.1;
                    // Hitung persentase coverage: (CCTV di area kritis / Total CCTV) * 100
                    const criticalCoveragePercent = totalCctv > 0 ? ((data.cctvAreaKritis || 0) / totalCctv * 100) : fallbackCriticalCoveragePercent;
                    const finalCoveragePercent = parseFloat(criticalCoveragePercent.toFixed(1));
                    window.initialCriticalCoveragePercentage = finalCoveragePercent;
                    window.chart2InitialValue = finalCoveragePercent;
                    if (criticalCoverageDescription) {
                        criticalCoverageDescription.textContent = `${finalCoveragePercent}% area kritis ter-cover CCTV`;
                    }
                    // Update chart2 dengan nilai yang sama persis dengan teks
                    const criticalCoverageChart = document.querySelector('#chart2');
                    if (criticalCoverageChart && criticalCoverageChart._apexcharts) {
                        criticalCoverageChart._apexcharts.updateSeries([finalCoveragePercent]);
                    }
                    
                    // Update Area Kritis Overview
                    animateNumber('modalJumlahAreaKritis', data.jumlahAreaKritis || 0, 800);
                    animateNumber('modalCctvAreaKritis', data.cctvAreaKritis || 0, 800);
                    animateNumber('modalCctvAreaNonKritis', data.cctvAreaNonKritis || 0, 800);
                    
                    // Update Detail Coverage Lokasi Table
                    updateDetailCoverageLokasiTable(data.detailCoverageLokasi || []);
                    
                    // Update Issues/Alert Cards
                    animateNumber('modalNotConnected', data.issues?.notConnected || 0, 800);
                    animateNumber('modalNotMirrored', data.issues?.notMirrored || 0, 800);
                    animateNumber('modalCriticalWithoutAutoAlert', data.issues?.criticalWithoutAutoAlert || 0, 800);
                    animateNumber('modalNotVerified', data.issues?.notVerified || 0, 800);
                    
                    // Update existing charts
                    updateSiteBarChart(data.distributionBySite || []);
                    updateStatusPieChart(data.statusBreakdown || []);
                    updateCompanyBarChart(data.distributionByCompany || []);
                    updateKondisiPieChart(data.kondisiBreakdown || []);
                    // Update mini chart (Top 9 perusahaan dengan CCTV terbanyak)
                    updateTopCompanyMiniChart(data.distributionByCompany || []);
                    
                    // Update new charts
                    updateKategoriCctvPieChart(data.kategoriCctvBreakdown || []);
                    updateKategoriAreaPieChart(data.kategoriAreaBreakdown || []);
                    updateKategoriAktivitasPieChart(data.kategoriAktivitasBreakdown || []);
                    updateTipeCctvBarChart(data.tipeCctvBreakdown || []);
                    updateJenisInstalasiBarChart(data.jenisInstalasiBreakdown || []);
                    updateTimeSeriesChart(data.timeSeriesData || []);
                    
                    // Update DataTable
                    if (companyCctvTable) {
                        companyCctvTable.ajax.reload();
                    }
                }
            })
            .catch(error => {
                console.error('Error loading chart stats:', error);
            });
    }
    
    // Function to update total CCTV count dynamically based on filters
    function updateTotalCctvCount() {
        const company = currentSelectedCompany || '__all__';
        const site = currentSelectedSite || '__all__';
        
        const totalCctvElement = document.getElementById('totalCctvCountDynamic');
        if (!totalCctvElement) return;
        
        // Add loading animation class
        totalCctvElement.classList.add('updating');
        
        fetch(`{{ route('hazard-detection.api.total-cctv-count') }}?company=${encodeURIComponent(company)}&site=${encodeURIComponent(site)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const targetValue = data.total || 0;
                    
                    // Animate number change
                    animateNumber('totalCctvCountDynamic', targetValue, 600);
                    
                    // Add pulse animation effect
                    totalCctvElement.classList.add('pulse-animation');
                    setTimeout(() => {
                        totalCctvElement.classList.remove('pulse-animation');
                    }, 600);
                } else {
                    console.error('Error fetching total CCTV count:', data.message);
                    totalCctvElement.textContent = '0';
                }
            })
            .catch(error => {
                console.error('Error loading total CCTV count:', error);
                totalCctvElement.textContent = '0';
            })
            .finally(() => {
                totalCctvElement.classList.remove('updating');
            });
    }
    
    // Function untuk update Site Bar Chart
    function updateSiteBarChart(data) {
        const chartElement = document.getElementById('chartSiteBar');
        if (!chartElement) return;
        
        const categories = data.map(item => item.label);
        const values = data.map(item => item.value);
        
        if (chartSiteBar) {
            chartSiteBar.updateOptions({
                series: [{
                    name: 'Jumlah CCTV',
                    data: values
                }],
                xaxis: {
                    categories: categories
                }
            });
        } else {
            chartSiteBar = new ApexCharts(chartElement, {
                series: [{
                    name: 'Jumlah CCTV',
                    data: values
                }],
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: {
                        show: true
                    }
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        borderRadius: 4
                    }
                },
                dataLabels: {
                    enabled: true
                },
                xaxis: {
                    categories: categories
                },
                colors: ['#3b82f6'],
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val + " CCTV"
                        }
                    }
                }
            });
            chartSiteBar.render();
        }
    }
    
    // Function untuk update Status Pie Chart
    function updateStatusPieChart(data) {
        const chartElement = document.getElementById('chartStatusPie');
        if (!chartElement) return;
        
        const labels = data.map(item => item.label);
        const values = data.map(item => item.value);
        
        if (chartStatusPie) {
            chartStatusPie.updateSeries(values);
            chartStatusPie.updateOptions({
                labels: labels
            });
        } else {
            chartStatusPie = new ApexCharts(chartElement, {
                series: values,
                chart: {
                    type: 'pie',
                    height: 350
                },
                labels: labels,
                colors: ['#10b981', '#6b7280', '#f59e0b', '#ef4444', '#8b5cf6'],
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val + " CCTV"
                        }
                    }
                }
            });
            chartStatusPie.render();
        }
    }
    
    // Function untuk update Company Bar Chart
    function updateCompanyBarChart(data) {
        const chartElement = document.getElementById('chartCompanyBar');
        if (!chartElement) return;
        
        const categories = data.map(item => item.label);
        const values = data.map(item => item.value);
        
        if (chartCompanyBar) {
            chartCompanyBar.updateOptions({
                series: [{
                    name: 'Jumlah CCTV',
                    data: values
                }],
                xaxis: {
                    categories: categories
                }
            });
        } else {
            chartCompanyBar = new ApexCharts(chartElement, {
                series: [{
                    name: 'Jumlah CCTV',
                    data: values
                }],
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: {
                        show: true
                    }
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        borderRadius: 4
                    }
                },
                dataLabels: {
                    enabled: true
                },
                xaxis: {
                    categories: categories
                },
                colors: ['#8b5cf6'],
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val + " CCTV"
                        }
                    }
                }
            });
            chartCompanyBar.render();
        }
    }

    // Function untuk update mini chart Top 9 Perusahaan (chart3)
    function updateTopCompanyMiniChart(data) {
        // Ambil 9 perusahaan dengan jumlah CCTV terbanyak
        const sorted = Array.isArray(data) ? [...data].sort(function(a, b) {
            const va = a && typeof a.value === 'number' ? a.value : parseFloat(a?.value) || 0;
            const vb = b && typeof b.value === 'number' ? b.value : parseFloat(b?.value) || 0;
            return vb - va;
        }) : [];

        const topCompanies = sorted.slice(0, 9);
        if (topCompanies.length === 0) {
            return;
        }

        const categories = topCompanies.map(function(item) {
            return item && item.label ? item.label : 'Perusahaan';
        });
        const values = topCompanies.map(function(item) {
            const v = item && typeof item.value === 'number' ? item.value : parseFloat(item?.value) || 0;
            return v;
        });

        // Ambil instance chart3 dari global (dibuat di public/build/js/index.js)
        const chart3 = window.chart3Instance;

        // Jika chart belum siap, coba lagi setelah sedikit delay
        if (!chart3) {
            setTimeout(function () {
                updateTopCompanyMiniChart(data);
            }, 300);
            return;
        }

        chart3.updateSeries([{
            name: 'Jumlah CCTV',
            data: values
        }]);

        chart3.updateOptions({
            xaxis: {
                categories: categories
            },
            tooltip: {
                x: {
                    show: true
                },
                y: {
                    formatter: function (val, opts) {
                        var idx = opts && typeof opts.dataPointIndex === 'number'
                            ? opts.dataPointIndex
                            : 0;
                        var company = categories[idx] || '';
                        return company + ': ' + val + ' CCTV';
                    }
                }
            }
        });
    }
    
    // Function untuk update Kondisi Pie Chart
    function updateKondisiPieChart(data) {
        const chartElement = document.getElementById('chartKondisiPie');
        if (!chartElement) return;
        
        const labels = data.map(item => item.label);
        const values = data.map(item => item.value);
        
        if (chartKondisiPie) {
            chartKondisiPie.updateSeries(values);
            chartKondisiPie.updateOptions({
                labels: labels
            });
        } else {
            chartKondisiPie = new ApexCharts(chartElement, {
                series: values,
                chart: {
                    type: 'pie',
                    height: 350
                },
                labels: labels,
                colors: ['#10b981', '#f59e0b', '#ef4444'],
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val + " CCTV"
                        }
                    }
                }
            });
            chartKondisiPie.render();
        }
    }

    // Function untuk update Kategori CCTV Pie Chart
    function updateKategoriCctvPieChart(data) {
        const chartElement = document.getElementById('chartKategoriCctvPie');
        if (!chartElement) return;
        
        const labels = data.map(item => item.label);
        const values = data.map(item => item.value);
        
        if (chartKategoriCctvPie) {
            chartKategoriCctvPie.updateSeries(values);
            chartKategoriCctvPie.updateOptions({ labels: labels });
        } else {
            chartKategoriCctvPie = new ApexCharts(chartElement, {
                series: values,
                chart: { type: 'pie', height: 350 },
                labels: labels,
                colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'],
                legend: { position: 'bottom' },
                tooltip: { y: { formatter: (val) => val + " CCTV" } }
            });
            chartKategoriCctvPie.render();
        }
    }

    // Function untuk update Kategori Area Pie Chart
    function updateKategoriAreaPieChart(data) {
        const chartElement = document.getElementById('chartKategoriAreaPie');
        if (!chartElement) return;
        
        // Urutkan data: Area Kritis dulu, kemudian Area Non Kritis
        const sortedData = [...data].sort((a, b) => {
            const labelA = (a.label || '').toLowerCase().trim();
            const labelB = (b.label || '').toLowerCase().trim();
            
            // Area Kritis harus muncul pertama
            if (labelA.includes('kritis') && !labelA.includes('non')) return -1;
            if (labelB.includes('kritis') && !labelB.includes('non')) return 1;
            if (labelA.includes('non') && labelA.includes('kritis')) return 1;
            if (labelB.includes('non') && labelB.includes('kritis')) return -1;
            return 0;
        });
        
        const labels = sortedData.map(item => item.label);
        const values = sortedData.map(item => item.value);
        
        // Warna khusus untuk area kritis (merah) vs non-kritis (hijau)
        // kategori_area_tercapture hanya ada 2 nilai: "Area Non Kritis" dan "Area Kritis"
        const colors = labels.map(label => {
            const lowerLabel = (label || '').toLowerCase().trim();
            // Cek spesifik untuk "Area Kritis" (tanpa "non")
            if (lowerLabel === 'area kritis' || (lowerLabel.includes('kritis') && !lowerLabel.includes('non'))) {
                return '#ef4444'; // Merah untuk Area Kritis
            } else {
                return '#10b981'; // Hijau untuk Area Non Kritis atau lainnya
            }
        });
        
        if (chartKategoriAreaPie) {
            chartKategoriAreaPie.updateSeries(values);
            chartKategoriAreaPie.updateOptions({ labels: labels, colors: colors });
        } else {
            chartKategoriAreaPie = new ApexCharts(chartElement, {
                series: values,
                chart: { type: 'pie', height: 350 },
                labels: labels,
                colors: colors,
                legend: { position: 'bottom' },
                tooltip: { y: { formatter: (val) => val + " CCTV" } }
            });
            chartKategoriAreaPie.render();
        }
    }

    // Function untuk update Kategori Aktivitas Pie Chart
    function updateKategoriAktivitasPieChart(data) {
        const chartElement = document.getElementById('chartKategoriAktivitasPie');
        if (!chartElement) return;
        
        const labels = data.map(item => item.label);
        const values = data.map(item => item.value);
        
        // Warna khusus untuk aktivitas kritis (merah/oranye) vs non-kritis (hijau/abu-abu)
        const colors = labels.map(label => {
            const lowerLabel = label.toLowerCase();
            if (lowerLabel.includes('kritis') || lowerLabel.includes('critical')) {
                return '#f59e0b'; // Oranye
            } else {
                return '#6b7280'; // Abu-abu
            }
        });
        
        if (chartKategoriAktivitasPie) {
            chartKategoriAktivitasPie.updateSeries(values);
            chartKategoriAktivitasPie.updateOptions({ labels: labels, colors: colors });
        } else {
            chartKategoriAktivitasPie = new ApexCharts(chartElement, {
                series: values,
                chart: { type: 'pie', height: 350 },
                labels: labels,
                colors: colors,
                legend: { position: 'bottom' },
                tooltip: { y: { formatter: (val) => val + " CCTV" } }
            });
            chartKategoriAktivitasPie.render();
        }
    }

    // Function untuk update Tipe CCTV Bar Chart
    function updateTipeCctvBarChart(data) {
        const chartElement = document.getElementById('chartTipeCctvBar');
        if (!chartElement) return;
        
        const categories = data.map(item => item.label);
        const values = data.map(item => item.value);
        
        if (chartTipeCctvBar) {
            chartTipeCctvBar.updateOptions({
                series: [{ name: 'Jumlah CCTV', data: values }],
                xaxis: { categories: categories }
            });
        } else {
            chartTipeCctvBar = new ApexCharts(chartElement, {
                series: [{ name: 'Jumlah CCTV', data: values }],
                chart: { type: 'bar', height: 350, toolbar: { show: true } },
                plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
                dataLabels: { enabled: true },
                xaxis: { categories: categories },
                colors: ['#06b6d4'],
                tooltip: { y: { formatter: (val) => val + " CCTV" } }
            });
            chartTipeCctvBar.render();
        }
    }

    // Function untuk update Jenis Instalasi Bar Chart
    function updateJenisInstalasiBarChart(data) {
        const chartElement = document.getElementById('chartJenisInstalasiBar');
        if (!chartElement) return;
        
        const categories = data.map(item => item.label);
        const values = data.map(item => item.value);
        
        if (chartJenisInstalasiBar) {
            chartJenisInstalasiBar.updateOptions({
                series: [{ name: 'Jumlah CCTV', data: values }],
                xaxis: { categories: categories }
            });
        } else {
            chartJenisInstalasiBar = new ApexCharts(chartElement, {
                series: [{ name: 'Jumlah CCTV', data: values }],
                chart: { type: 'bar', height: 350, toolbar: { show: true } },
                plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
                dataLabels: { enabled: true },
                xaxis: { categories: categories },
                colors: ['#8b5cf6'],
                tooltip: { y: { formatter: (val) => val + " CCTV" } }
            });
            chartJenisInstalasiBar.render();
        }
    }

    // Function untuk update Time Series Chart
    function updateTimeSeriesChart(data) {
        const chartElement = document.getElementById('chartTimeSeries');
        if (!chartElement) return;
        
        const categories = data.map(item => item.label);
        const values = data.map(item => item.value);
        
        if (chartTimeSeries) {
            chartTimeSeries.updateOptions({
                series: [{ name: 'Jumlah CCTV', data: values }],
                xaxis: { categories: categories }
            });
        } else {
            chartTimeSeries = new ApexCharts(chartElement, {
                series: [{ name: 'Jumlah CCTV', data: values }],
                chart: { type: 'area', height: 350, toolbar: { show: true }, zoom: { enabled: true } },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.3 } },
                xaxis: { categories: categories },
                colors: ['#3b82f6'],
                tooltip: { y: { formatter: (val) => val + " CCTV" } }
            });
            chartTimeSeries.render();
        }
    }

    // Function untuk update Detail Coverage Lokasi Table
    function updateDetailCoverageLokasiTable(data) {
        const tbody = document.getElementById('detailCoverageLokasiTableBody');
        if (!tbody) return;
        
        if (!data || data.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">
                        Tidak ada data coverage lokasi.
                    </td>
                </tr>
            `;
            return;
        }
        
        // Urutkan: kritis dulu, kemudian non kritis
        const sortedData = [...data].sort((a, b) => {
            if (a.is_kritis && !b.is_kritis) return -1;
            if (!a.is_kritis && b.is_kritis) return 1;
            return b.jumlah_cctv - a.jumlah_cctv;
        });
        
        let rowsHtml = '';
        sortedData.forEach((lokasi, index) => {
            const statusBadge = lokasi.is_kritis 
                ? '<span class="badge bg-danger px-3 py-2">Area Kritis</span>'
                : '<span class="badge bg-success px-3 py-2">Area Non Kritis</span>';
            
            const jumlahBadge = lokasi.is_kritis
                ? `<span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2">${lokasi.jumlah_cctv.toLocaleString('id-ID')} CCTV</span>`
                : `<span class="badge bg-success bg-opacity-10 text-success px-3 py-2">${lokasi.jumlah_cctv.toLocaleString('id-ID')} CCTV</span>`;
            
            rowsHtml += `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <span class="fw-semibold">${escapeHtml(lokasi.nama_lokasi || 'Tidak Diketahui')}</span>
                    </td>
                    <td class="text-end">
                        ${jumlahBadge}
                    </td>
                    <td class="text-center">
                        ${statusBadge}
                    </td>
                </tr>
            `;
        });
        
        tbody.innerHTML = rowsHtml;
    }

    // Inisialisasi DataTable saat modal dibuka
    const totalCctvModal = document.getElementById('totalCctvModal');
    if (totalCctvModal) {
        totalCctvModal.addEventListener('shown.bs.modal', function () {
            if (!companyCctvTable) {
                companyCctvTable = $('#companyCctvTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('hazard-detection.api.company-cctv-data') }}",
                        type: "GET",
                        data: function (d) {
                            d.company = currentSelectedCompany;
                            d.site = currentSelectedSite;
                        },
                        dataFilter: function(data) {
                            var json = jQuery.parseJSON(data);
                            document.getElementById('companyCctvCount').textContent = `${json.recordsFiltered} CCTV`;
                            return JSON.stringify(json);
                        },
                        error: function(xhr, error, thrown) {
                            console.error("DataTables AJAX error:", thrown, xhr);
                            document.getElementById('companyCctvCount').textContent = '0 CCTV';
                        }
                    },
                    columns: [
                        { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '50px' },
                        { data: 'site', name: 'site', width: '100px' },
                        { data: 'perusahaan', name: 'perusahaan', width: '150px' },
                        { data: 'no_cctv', name: 'no_cctv', className: 'fw-semibold text-primary', width: '120px' },
                        { data: 'nama_cctv', name: 'nama_cctv', width: '150px' },
                        { data: 'status', name: 'status', orderable: false, searchable: false, width: '100px' },
                        { data: 'kondisi', name: 'kondisi', orderable: false, searchable: false, width: '100px' },
                        { data: 'coverage_lokasi', name: 'coverage_lokasi', width: '150px' },
                        { data: 'coverage_detail_lokasi', name: 'coverage_detail_lokasi', width: '150px' },
                        { data: 'kategori_area_tercapture', name: 'kategori_area_tercapture', width: '150px' },
                        { data: 'lokasi_pemasangan', name: 'lokasi_pemasangan', width: '150px' }
                    ],
                    order: [[3, 'asc']],
                    pageLength: 25,
                    scrollX: true,
                    scrollY: '400px',
                    scrollCollapse: true,
                    autoWidth: false,
                    language: {
                        processing: "Memproses data...",
                        search: "Cari:",
                        lengthMenu: "Tampilkan _MENU_ data per halaman",
                        info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                        infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                        infoFiltered: "(disaring dari _MAX_ total data)",
                        paginate: {
                            first: "Pertama",
                            last: "Terakhir",
                            next: "Selanjutnya",
                            previous: "Sebelumnya"
                        },
                        emptyTable: "Klik perusahaan di tabel sebelah kiri untuk menampilkan daftar CCTV.",
                        zeroRecords: "Tidak ada data yang cocok dengan pencarian"
                    },
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                });
            }
        });

        // Handler untuk filter otomatis saat dropdown berubah di modal
        // Menggunakan event delegation untuk memastikan event listener selalu bekerja
        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'filterCompany') {
                currentSelectedCompany = e.target.value || '__all__';
                updateFilterLabel();
                // Update filter di halaman utama (dropdown button di map card)
                const mainFilterCompanyText = document.getElementById('mainFilterCompanyText');
                if (mainFilterCompanyText) {
                    const selectedOption = e.target.options[e.target.selectedIndex];
                    mainFilterCompanyText.textContent = selectedOption ? selectedOption.textContent : 'Semua Perusahaan';
                }
                // Update filter di header juga
                const headerFilterCompanyText = document.getElementById('headerFilterCompanyText');
                if (headerFilterCompanyText) {
                    const selectedOption = e.target.options[e.target.selectedIndex];
                    headerFilterCompanyText.textContent = selectedOption ? selectedOption.textContent : 'Semua Perusahaan';
                }
                // Update semua statistik berdasarkan filter yang dipilih
                loadChartStats();
                loadAreaKritisOverview();
                updateTotalCctvCount();
            }
            
            if (e.target && e.target.id === 'filterSite') {
                currentSelectedSite = e.target.value || '__all__';
                updateFilterLabel();
                // Update filter di halaman utama (dropdown button di map card)
                const mainFilterSiteText = document.getElementById('mainFilterSiteText');
                if (mainFilterSiteText) {
                    const selectedOption = e.target.options[e.target.selectedIndex];
                    mainFilterSiteText.textContent = selectedOption ? selectedOption.textContent : 'Semua Site';
                }
                // Update filter di header juga
                const headerFilterSiteText = document.getElementById('headerFilterSiteText');
                if (headerFilterSiteText) {
                    const selectedOption = e.target.options[e.target.selectedIndex];
                    headerFilterSiteText.textContent = selectedOption ? selectedOption.textContent : 'Semua Site';
                }
                // Update currentSiteFilter untuk filter map dan hazard list
                currentSiteFilter = (currentSelectedSite !== '__all__') ? currentSelectedSite : '';
                filterBySite(currentSiteFilter);
                filterHazardListView(currentSiteFilter);
                updateStatisticsBySite(currentSiteFilter);
                // Update semua statistik berdasarkan filter yang dipilih
                loadChartStats();
                loadAreaKritisOverview();
                updateTotalCctvCount();
            }
        });
        
        // Handler untuk reset filter di modal
        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'btnResetFilter') {
                currentSelectedCompany = '__all__';
                currentSelectedSite = '__all__';
                
                const filterCompany = document.getElementById('filterCompany');
                const filterSite = document.getElementById('filterSite');
                
                if (filterCompany) {
                    filterCompany.value = '__all__';
                }
                if (filterSite) {
                    filterSite.value = '__all__';
                }
                
                // Update filter di halaman utama (dropdown button di map card)
                const mainFilterCompanyText = document.getElementById('mainFilterCompanyText');
                const mainFilterSiteText = document.getElementById('mainFilterSiteText');
                if (mainFilterCompanyText) {
                    mainFilterCompanyText.textContent = 'Semua Perusahaan';
                }
                if (mainFilterSiteText) {
                    mainFilterSiteText.textContent = 'Semua Site';
                }
                // Update filter di header juga
                const headerFilterCompanyText = document.getElementById('headerFilterCompanyText');
                const headerFilterSiteText = document.getElementById('headerFilterSiteText');
                if (headerFilterCompanyText) {
                    headerFilterCompanyText.textContent = 'Semua Perusahaan';
                }
                if (headerFilterSiteText) {
                    headerFilterSiteText.textContent = 'Semua Site';
                }
                
                updateFilterLabel();
                
                // Load chart stats
                loadChartStats();
                // Update area kritis overview saat filter di-reset
                loadAreaKritisOverview();
            }
        });

        // Reset saat modal ditutup
        totalCctvModal.addEventListener('hidden.bs.modal', function () {
            currentSelectedCompany = '__all__';
            document.getElementById('companyCctvCompanyLabel').textContent = 'Pilih perusahaan untuk melihat rincian';
            document.getElementById('companyCctvCount').textContent = '0 CCTV';
            document.querySelectorAll('.company-row-trigger').forEach(r => r.classList.remove('table-active'));
            
            // Reset statistik
            document.getElementById('companyStatsAktif').textContent = '0';
            document.getElementById('companyStatsNonAktif').textContent = '0';
            document.getElementById('companyStatsAreaKritis').textContent = '0';
            
            if (companyCctvTable) {
                companyCctvTable.clear().draw();
            }
        });
    }

    // Function to toggle layer visibility
    function toggleLayerVisibility(layerType, show) {
        layerVisibility[layerType] = show;
        
        let targetLayer = null;
        switch(layerType) {
            case 'cctv':
                targetLayer = cctvLayer;
                break;
            case 'hazard':
                targetLayer = hazardLayer;
                break;
            case 'gr':
                targetLayer = grLayer;
                break;
            case 'insiden':
                targetLayer = insidenLayer;
                break;
            case 'unit':
                targetLayer = unitVehicleLayer;
                break;
            case 'gps':
                targetLayer = userGpsLayer;
                break;
        }
        
        if (targetLayer) {
            targetLayer.setVisible(show);
        }
        
        // Update button state
        const toggleBtn = document.getElementById('toggle' + layerType.charAt(0).toUpperCase() + layerType.slice(1));
        if (toggleBtn) {
            if (show) {
                toggleBtn.classList.add('active');
            } else {
                toggleBtn.classList.remove('active');
            }
        }
    }
    
    // Event listeners for layer toggle buttons
    document.addEventListener('DOMContentLoaded', function() {
        // CCTV toggle
        const toggleCctvBtn = document.getElementById('toggleCctv');
        if (toggleCctvBtn) {
            toggleCctvBtn.addEventListener('click', function() {
                const isActive = this.classList.contains('active');
                toggleLayerVisibility('cctv', !isActive);
            });
        }
        
        // Hazard toggle
        const toggleHazardBtn = document.getElementById('toggleHazard');
        if (toggleHazardBtn) {
            toggleHazardBtn.addEventListener('click', function() {
                const isActive = this.classList.contains('active');
                toggleLayerVisibility('hazard', !isActive);
            });
        }
        
        // GR toggle
        const toggleGrBtn = document.getElementById('toggleGr');
        if (toggleGrBtn) {
            toggleGrBtn.addEventListener('click', function() {
                const isActive = this.classList.contains('active');
                toggleLayerVisibility('gr', !isActive);
            });
        }
        
        // Insiden toggle
        const toggleInsidenBtn = document.getElementById('toggleInsiden');
        if (toggleInsidenBtn) {
            toggleInsidenBtn.addEventListener('click', function() {
                const isActive = this.classList.contains('active');
                toggleLayerVisibility('insiden', !isActive);
            });
        }
        
        // Unit toggle
        const toggleUnitBtn = document.getElementById('toggleUnit');
        if (toggleUnitBtn) {
            toggleUnitBtn.addEventListener('click', function() {
                const isActive = this.classList.contains('active');
                toggleLayerVisibility('unit', !isActive);
            });
        }
        
        // GPS toggle
        const toggleGpsBtn = document.getElementById('toggleGps');
        if (toggleGpsBtn) {
            toggleGpsBtn.addEventListener('click', function() {
                const isActive = this.classList.contains('active');
                toggleLayerVisibility('gps', !isActive);
            });
        }
        
        // Evaluasi toggle
        const toggleEvaluasiBtn = document.getElementById('toggleEvaluasi');
        if (toggleEvaluasiBtn) {
            toggleEvaluasiBtn.addEventListener('click', function() {
                const isActive = this.classList.contains('active');
                toggleEvaluasiVisibility(!isActive);
            });
        }
        
        // Reset filter button - juga reset layer visibility
        const btnResetMainFilter = document.getElementById('btnResetMainFilter');
        if (btnResetMainFilter) {
            btnResetMainFilter.addEventListener('click', function() {
                // Reset all layer visibility to true
                toggleLayerVisibility('cctv', true);
                toggleLayerVisibility('hazard', true);
                toggleLayerVisibility('gr', true);
                toggleLayerVisibility('insiden', true);
                toggleLayerVisibility('unit', true);
                toggleLayerVisibility('gps', true);
                toggleEvaluasiVisibility(false);
            });
        }
    });
    
    // Global variables for evaluation
    let evaluationOverlays = [];
    let evaluationEnabled = false;
    
    // Function to toggle evaluasi visibility
    function toggleEvaluasiVisibility(show) {
        evaluationEnabled = show;
        const toggleBtn = document.getElementById('toggleEvaluasi');
        if (toggleBtn) {
            if (show) {
                toggleBtn.classList.add('active');
                showEvaluationAlerts();
            } else {
                toggleBtn.classList.remove('active');
                hideEvaluationAlerts();
            }
        }
    }
    
    // Function to normalize location name for better matching
    function normalizeLocationName(name) {
        if (!name) return '';
        return name.toLowerCase()
            .trim()
            .replace(/\s+/g, ' ') // Multiple spaces to single space
            .replace(/[_-]/g, ' ') // Replace underscore and dash with space
            .replace(/[^\w\s]/g, '') // Remove special characters except word and space
            .replace(/\b(area|lokasi|zone|zona|site|tempat)\b/gi, '') // Remove common location words
            .trim();
    }
    
    // Function to check if coordinate is inside polygon geometry
    function isCoordinateInGeometry(latitude, longitude, geometry) {
        if (!latitude || !longitude || !geometry) return false;
        
        try {
            // Convert lat/lng to map projection (EPSG:3857)
            const coordinate = ol.proj.fromLonLat([parseFloat(longitude), parseFloat(latitude)]);
            
            const geometryType = geometry.getType();
            
            // For Polygon and MultiPolygon, check if point is inside
            if (geometryType === 'Polygon' || geometryType === 'MultiPolygon') {
                // Use intersectsCoordinate for quick check
                if (geometry.intersectsCoordinate(coordinate)) {
                    return true;
                }
                
                // For more accurate check, create a point and check if it's inside polygon
                const point = new ol.geom.Point(coordinate);
                
                // Check if point intersects with polygon using intersects method
                // Note: intersectsGeometry doesn't exist in OpenLayers, use intersectsCoordinate instead
                try {
                    if (geometry.intersectsCoordinate(coordinate)) {
                        return true;
                    }
                } catch (e) {
                    // Fallback: check if coordinate is within extent
                    const extent = geometry.getExtent();
                    if (extent && ol.extent.containsCoordinate(extent, coordinate)) {
                        // Additional check: use ray casting algorithm for more accurate point-in-polygon
                        // For now, if it's in extent, we'll consider it as inside
                        return true;
                    }
                }
                
                // Also check if point is very close to polygon boundary (within 10 meters tolerance)
                // This handles cases where coordinate is slightly outside due to GPS accuracy
                try {
                    const closestPoint = geometry.getClosestPoint(coordinate);
                    const distance = ol.coordinate.distance(coordinate, closestPoint);
                    // Convert distance from map units to meters (approximate: 1 map unit ≈ 1 meter at equator)
                    // For more accuracy, we can use ol.sphere.getDistance but it requires lon/lat
                    const distanceInMeters = distance; // Approximate conversion
                    if (distanceInMeters < 10) { // 10 meters tolerance
                        return true;
                    }
                } catch (e) {
                    // Ignore error in closest point calculation
                }
            } else {
                // For other geometry types, use intersectsCoordinate
                if (geometry.intersectsCoordinate(coordinate)) {
                    return true;
                }
            }
            
            return false;
        } catch (e) {
            console.warn('Error checking coordinate in geometry:', e);
            return false;
        }
    }
    
    // Function to check if area has SAP report today
    // SAP terdiri dari: Hazard, Inspeksi, Observasi, Coaching, OAK
    // Semua jenis SAP sudah termasuk dalam sapDataForSidebar dari getSapDataFromClickHouse()
    // Parameters:
    //   - areaType: 'area_kerja' or 'area_cctv'
    //   - idLokasi: ID lokasi untuk area kerja
    //   - lokasiName: Nama lokasi area kerja atau area CCTV
    //   - nomorCctv: Nomor CCTV untuk area CCTV
    //   - cctvName: Nama CCTV untuk area CCTV
    //   - featureGeometry: OpenLayers geometry dari feature area (optional, untuk pengecekan koordinat)
    function hasSapReportToday(areaType, idLokasi, lokasiName, nomorCctv, cctvName, featureGeometry) {
        // Logging untuk debugging
        const logPrefix = `[SAP Check] ${areaType} - ${lokasiName || cctvName || nomorCctv || 'Unknown'}:`;
        console.log(`${logPrefix} Memulai pengecekan...`);
        
        if (!sapDataForSidebar || sapDataForSidebar.length === 0) {
            console.log(`${logPrefix} Tidak ada data SAP di sidebar`);
            return false;
        }
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const todayStr = today.toISOString().split('T')[0];
        
        // Filter SAP data for today
        // Mengecek semua jenis SAP: Hazard, Inspeksi (INSPEKSI_HAZARD), Observasi, Coaching, OAK
        const sapToday = sapDataForSidebar.filter(sap => {
            if (!sap.tanggal_pelaporan && !sap.detected_at) return false;
            try {
                const sapDate = new Date(sap.tanggal_pelaporan || sap.detected_at);
                sapDate.setHours(0, 0, 0, 0);
                const sapDateStr = sapDate.toISOString().split('T')[0];
                return sapDateStr === todayStr;
            } catch (e) {
                return false;
            }
        });
        
        console.log(`${logPrefix} Total SAP hari ini: ${sapToday.length} dari ${sapDataForSidebar.length} total`);
        
        if (sapToday.length === 0) {
            console.log(`${logPrefix} Tidak ada SAP hari ini`);
            return false;
        }
        
        // Normalize area location name
        let normalizedAreaName = '';
        if (areaType === 'area_kerja') {
            normalizedAreaName = normalizeLocationName(lokasiName || '');
        } else if (areaType === 'area_cctv') {
            normalizedAreaName = normalizeLocationName(cctvName || nomorCctv || '');
        }
        
        console.log(`${logPrefix} Nama area dinormalisasi: "${normalizedAreaName}"`);
        
        // Check if any SAP (dari semua jenis: Hazard, Inspeksi, Observasi, Coaching, OAK) matches the area
        let matchCount = 0;
        let coordinateMatchCount = 0;
        let nameMatchCount = 0;
        
        for (const sap of sapToday) {
            const sapLokasi = normalizeLocationName(sap.lokasi || '');
            const sapDetailLokasi = normalizeLocationName(sap.detail_lokasi || '');
            const jenisLaporan = sap.jenis_laporan || sap.source_type || sap.type || 'SAP';
            
            let matched = false;
            let matchReason = '';
            
            // Method 1: Check coordinate geografis (jika ada geometry dan koordinat SAP)
            if (featureGeometry) {
                const sapLat = sap.latitude || (sap.location && sap.location.lat) || null;
                const sapLng = sap.longitude || (sap.location && sap.location.lng) || null;
                
                if (sapLat && sapLng && !isNaN(parseFloat(sapLat)) && !isNaN(parseFloat(sapLng))) {
                    if (isCoordinateInGeometry(parseFloat(sapLat), parseFloat(sapLng), featureGeometry)) {
                        matched = true;
                        matchReason = `Koordinat (${sapLat}, ${sapLng})`;
                        coordinateMatchCount++;
                        console.log(`${logPrefix} ✓ MATCH via koordinat - ${jenisLaporan} #${sap.task_number || 'N/A'}: ${matchReason}`);
                        return true; // Return immediately if coordinate matches
                    }
                }
            }
            
            // Method 2: Check nama lokasi (normalized string matching)
            if (!matched && normalizedAreaName) {
                // Check if normalized area name matches SAP location
                const nameMatch = (
                    (sapLokasi && (sapLokasi.includes(normalizedAreaName) || normalizedAreaName.includes(sapLokasi))) ||
                    (sapDetailLokasi && (sapDetailLokasi.includes(normalizedAreaName) || normalizedAreaName.includes(sapDetailLokasi)))
                );
                
                if (nameMatch) {
                    matched = true;
                    matchReason = `Nama lokasi: "${sapLokasi || sapDetailLokasi}"`;
                    nameMatchCount++;
                    console.log(`${logPrefix} ✓ MATCH via nama - ${jenisLaporan} #${sap.task_number || 'N/A'}: ${matchReason}`);
                    return true; // Return immediately if name matches
                }
            }
        }
        
        // Log summary
        console.log(`${logPrefix} Tidak ada match. Pengecekan: ${sapToday.length} SAP, ${coordinateMatchCount} match koordinat, ${nameMatchCount} match nama`);
        
        return false;
    }
    
    // Function to show evaluation alerts on map
    function showEvaluationAlerts() {
        // Clear existing overlays
        hideEvaluationAlerts();
        
        if (!evaluationEnabled) {
            console.log('Evaluation toggle is off');
            return;
        }
        
        if (!window.areaCctvLayers || !window.areaKerjaLayers) {
            console.log('Area layers not loaded yet, retrying...');
            // Retry after a short delay
            setTimeout(() => {
                if (evaluationEnabled) {
                    showEvaluationAlerts();
                }
            }, 1000);
            return;
        }
        
        const allLayers = [...(window.areaCctvLayers || []), ...(window.areaKerjaLayers || [])];
        let alertCount = 0;
        
        allLayers.forEach(layer => {
            // Skip if layer is not visible
            if (!layer.getVisible()) return;
            
            const source = layer.getSource();
            if (!source) return;
            
            const features = source.getFeatures();
            features.forEach(feature => {
                const props = feature.getProperties();
                const hasNomorCctv = 'nomor_cctv' in props;
                const hasIdLokasi = 'id_lokasi' in props;
                
                let areaType = null;
                let idLokasi = null;
                let lokasiName = null;
                let nomorCctv = null;
                let cctvName = null;
                
                if (hasNomorCctv) {
                    areaType = 'area_cctv';
                    nomorCctv = (props.nomor_cctv && props.nomor_cctv !== null && props.nomor_cctv !== 'null') ? props.nomor_cctv : null;
                    cctvName = (props.nama_cctv && props.nama_cctv !== null && props.nama_cctv !== 'null') ? props.nama_cctv : null;
                    lokasiName = props.coverage_lokasi || props.lokasi_pemasangan || props.coverage_detail_lokasi || props.lokasi || '';
                } else if (hasIdLokasi) {
                    areaType = 'area_kerja';
                    idLokasi = (props.id_lokasi && props.id_lokasi !== null && props.id_lokasi !== 'null') ? props.id_lokasi : null;
                    lokasiName = (props.lokasi && props.lokasi !== null && props.lokasi !== 'null') ? props.lokasi : null;
                }
                
                if (!areaType) return;
                
                // Get geometry for coordinate checking
                const geometry = feature.getGeometry();
                
                // Check if area has SAP report today
                // Pass geometry untuk pengecekan koordinat geografis
                const hasSap = hasSapReportToday(areaType, idLokasi, lokasiName, nomorCctv, cctvName, geometry);
                
                if (!hasSap) {
                    // Get geometry center for label
                    if (geometry) {
                        const extent = geometry.getExtent();
                        const center = ol.extent.getCenter(extent);
                        
                        // Create label element (permanent text label)
                        const labelElement = document.createElement('div');
                        labelElement.className = 'evaluation-alert-label';
                        labelElement.style.cssText = `
                            background: rgba(239, 68, 68, 0.95);
                            color: #ffffff;
                            border: 2px solid #dc2626;
                            border-radius: 6px;
                            padding: 6px 12px;
                            font-size: 12px;
                            font-weight: 600;
                            white-space: nowrap;
                            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
                            z-index: 1000;
                            pointer-events: none;
                            text-align: center;
                            display: inline-block;
                            line-height: 1.4;
                        `;
                        
                        const areaName = hasNomorCctv 
                            ? (cctvName || nomorCctv || 'Area CCTV')
                            : (lokasiName || 'Area Kerja');
                        
                        labelElement.innerHTML = `
                            <span style="display: inline-flex; align-items: center; gap: 4px;">
                                <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">warning</i>
                                <span>Area ini belum ada laporan SAP</span>
                            </span>
                        `;
                        
                        const overlay = new ol.Overlay({
                            element: labelElement,
                            position: center,
                            positioning: 'center-center',
                            stopEvent: false,
                            offset: [0, -20]
                        });
                        
                        map.addOverlay(overlay);
                        evaluationOverlays.push(overlay);
                        alertCount++;
                    }
                }
            });
        });
        
        if (alertCount > 0) {
            console.log(`✓ Evaluation alerts: ${alertCount} area(s) tanpa laporan SAP hari ini`);
        } else {
            console.log('✓ Semua area sudah memiliki laporan SAP hari ini');
        }
    }
    
    // Function to hide evaluation alerts
    function hideEvaluationAlerts() {
        evaluationOverlays.forEach(overlay => {
            map.removeOverlay(overlay);
        });
        evaluationOverlays = [];
    }

    // Sidebar Panel Management - variabel sudah didefinisikan di atas
    // Initialize sidebar data
    // Data CCTV diambil langsung dari database (cctvLocations), bukan dari WMS atau GeoJSON
    function initializeSidebarData() {
        // Pastikan menggunakan data dari database
        filteredSidebarData.cctv = [...(cctvLocations || [])];
        // SAP data sudah di-load oleh loadSapDataByWeek() berdasarkan week filter
        // Jangan overwrite jika sudah ada data dari week filter
        // Gunakan sapDataForSidebar jika tersedia (semua data), jika tidak gunakan sapData
        if (filteredSidebarData.sap.length === 0) {
            if (typeof sapDataForSidebar !== 'undefined' && sapDataForSidebar && sapDataForSidebar.length > 0) {
                filteredSidebarData.sap = [...sapDataForSidebar]; // Semua data untuk sidebar
            } else if (sapData && sapData.length > 0) {
                filteredSidebarData.sap = [...sapData];
            }
        }
        filteredSidebarData.insiden = [...(insidenDataset || [])];
        // Update unit data jika sudah ada
        if (unitVehicles && unitVehicles.length > 0) {
            filteredSidebarData.unit = [...unitVehicles];
            console.log('Unit data initialized in sidebar:', filteredSidebarData.unit.length, 'units');
        } else {
            filteredSidebarData.unit = [];
            console.log('Unit data is empty, will be loaded by refreshUnitVehicles()');
        }
        // GPS data akan di-load oleh loadUserGpsData()
        
        // Initialize Control Room data - group CCTV by control_room
        initializeControlRoomData();
        
        updateTabCounts();
        renderSidebarTab(currentSidebarTab);
        
        // Jika tab unit aktif, render list unit
        if (currentSidebarTab === 'unit' && filteredSidebarData.unit.length > 0) {
            renderUnitList(filteredSidebarData.unit);
        }
    }
    
    // Update tab counts
    function updateTabCounts() {
        const cctvCount = document.getElementById('cctvTabCount');
        const sapCount = document.getElementById('sapTabCount');
        const insidenCount = document.getElementById('insidenTabCount');
        const unitCount = document.getElementById('unitTabCount');
        const gpsCount = document.getElementById('gpsTabCount');
        const pjaCount = document.getElementById('pjaTabCount');
        
        // Update icon toolbar badges
        const iconToolbarBadges = {
            'cctvTabCount': 'iconToolbarCctvCount',
            'sapTabCount': 'iconToolbarSapCount',
            'insidenTabCount': 'iconToolbarInsidenCount',
            'unitTabCount': 'iconToolbarUnitCount',
            'gpsTabCount': 'iconToolbarGpsCount',
            'pjaTabCount': 'iconToolbarPjaCount',
            'controlroomTabCount': 'iconToolbarControlroomCount'
        };
        
        // Sync counts to icon toolbar badges
        Object.keys(iconToolbarBadges).forEach(tabCountId => {
            const tabCountEl = document.getElementById(tabCountId);
            const iconBadgeEl = document.getElementById(iconToolbarBadges[tabCountId]);
            if (tabCountEl && iconBadgeEl) {
                const count = tabCountEl.textContent.trim();
                if (count && count !== '0' && count !== '...') {
                    iconBadgeEl.textContent = count;
                } else {
                    iconBadgeEl.textContent = '';
                }
            }
        });
        
        if (cctvCount) cctvCount.textContent = filteredSidebarData.cctv.length;
        // Untuk SAP, gunakan semua data per week untuk count (bukan hanya data hari ini)
        if (sapCount) {
            const sapCountValue = (typeof sapDataAllWeek !== 'undefined' && sapDataAllWeek.length > 0) 
                ? sapDataAllWeek.length 
                : filteredSidebarData.sap.length;
            sapCount.textContent = sapCountValue;
        }
        if (insidenCount) insidenCount.textContent = filteredSidebarData.insiden.length;
        if (unitCount) unitCount.textContent = filteredSidebarData.unit.length;
        if (gpsCount) gpsCount.textContent = filteredSidebarData.gps.length;
        const controlroomCount = document.getElementById('controlroomTabCount');
        if (controlroomCount) controlroomCount.textContent = filteredSidebarData.controlroom.length;
        if (pjaCount) pjaCount.textContent = filteredSidebarData.pja.length;
        
        // Update icon toolbar badges after updating tab counts
        Object.keys(iconToolbarBadges).forEach(tabCountId => {
            const tabCountEl = document.getElementById(tabCountId);
            const iconBadgeEl = document.getElementById(iconToolbarBadges[tabCountId]);
            if (tabCountEl && iconBadgeEl) {
                const count = tabCountEl.textContent.trim();
                if (count && count !== '0' && count !== '...') {
                    iconBadgeEl.textContent = count;
                } else {
                    iconBadgeEl.textContent = '';
                }
            }
        });
    }
    
    // Get avatar color based on first letter
    function getAvatarColor(letter) {
        const colors = [
            '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6',
            '#ec4899', '#06b6d4', '#f97316', '#84cc16', '#6366f1'
        ];
        const index = (letter.charCodeAt(0) - 65) % colors.length;
        return colors[index >= 0 ? index : 0];
    }
    
    // Get first letter for avatar
    function getFirstLetter(text) {
        if (!text) return '?';
        const firstChar = text.trim().charAt(0).toUpperCase();
        return /[A-Z0-9]/.test(firstChar) ? firstChar : '?';
    }
    
    // Format timestamp
    function formatTimestamp(dateString) {
        if (!dateString) return '';
        
        try {
            const date = new Date(dateString);
            // Kurangi 7 jam dari waktu database
            date.setHours(date.getHours() - 7);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const day = date.getDate();
            const month = months[date.getMonth()];
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            
            return `${day} ${month} ${year} ${hours}:${minutes} WITA`;
        } catch (e) {
            return dateString;
        }
    }
    
    // Format date with time (for history)
    function formatDateWIB(dateString) {
        if (!dateString) return 'N/A';
        
        try {
            const date = new Date(dateString);
            // Kurangi 7 jam dari waktu database
            date.setHours(date.getHours() - 7);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const day = date.getDate();
            const month = months[date.getMonth()];
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            
            return `${day} ${month} ${year}, ${hours}:${minutes} WITA`;
        } catch (e) {
            return dateString;
        }
    }
    
    // Render CCTV list
    // Data CCTV diambil langsung dari database, bukan dari WMS atau GeoJSON
    function renderCctvList(data) {
        const container = document.getElementById('cctvList');
        if (!container) return;
        
        // Pastikan data berasal dari database (cctvLocations)
        if (!data || data.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="material-icons-outlined">videocam_off</i>
                    <p>Tidak ada data CCTV</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = data.map((cctv, index) => {
            // Data dari database: nama_cctv, no_cctv, id, dll
            const name = cctv.name || cctv.nama_cctv || cctv.no_cctv || `CCTV ${cctv.id}`;
            const id = cctv.no_cctv || cctv.nomor_cctv || cctv.id || '';
            const fullId = cctv.id || '';
            const firstLetter = getFirstLetter(name);
            const avatarColor = getAvatarColor(firstLetter);
            
            // Gunakan kategori_area_tercapture dari database
            const kategoriArea = cctv.kategori_area_tercapture || '';
            
            // Get RTSP URL for streaming
            const rawRtspUrl = (cctv.rtsp_url && cctv.rtsp_url.trim() !== '') ? cctv.rtsp_url.trim() : '';
            const effectiveRtspUrl = rawRtspUrl || defaultCctvRtspUrl || '';
            
            return `
                <div class="sidebar-list-item" data-type="cctv" data-id="${cctv.id}" data-index="${index}" data-hazard-status="loading" data-cctv-name="${escapeHtml(name).replace(/"/g, '&quot;')}" data-rtsp-url="${escapeHtml(effectiveRtspUrl).replace(/"/g, '&quot;')}">
                    <div class="sidebar-list-item-header">
                        <div class="list-item-avatar" style="background-color: ${avatarColor};">
                            ${firstLetter}
                        </div>
                        <div class="list-item-content">
                            <div class="list-item-title">${name}</div>
                            <div class="list-item-subtitle">${id ? `${id}${fullId ? ` (${fullId})` : ''}` : `ID: ${fullId || index + 1}`}</div>
                            ${kategoriArea ? `<div class="list-item-time">${kategoriArea}</div>` : ''}
                        </div>
                        <div class="cctv-hazard-status-icon loading" title="Memeriksa status hazard inspeksi...">
                            <i class="material-icons-outlined" style="font-size: 14px;">hourglass_empty</i>
                        </div>
                        <i class="material-icons-outlined list-item-expand-icon">expand_more</i>
                    </div>
                    <div class="cctv-detail-section">
                        <div class="cctv-detail-loading">
                            <i class="material-icons-outlined" style="font-size: 24px; margin-bottom: 8px; opacity: 0.5;">hourglass_empty</i>
                            <div>Memuat detail...</div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        // Load hazard status for all CCTV
        loadCctvHazardStatus(data.map(c => c.id));
        
        // Add click handlers - toggle expand/collapse dan load details
        container.querySelectorAll('.sidebar-list-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // Prevent event bubbling untuk icon expand
                if (e.target.classList.contains('list-item-expand-icon')) {
                    e.stopPropagation();
                }
                
                const cctvId = this.dataset.id;
                const cctvData = data.find(c => c.id == cctvId);
                
                // Toggle expanded state
                const isExpanded = this.classList.contains('expanded');
                
                if (isExpanded) {
                    // Collapse
                    this.classList.remove('expanded');
                } else {
                    // Expand - load details
                    this.classList.add('expanded');
                    loadCctvDetails(cctvId, this);
                    
                    // Jika CCTV punya koordinat, zoom ke lokasi
                    if (cctvData) {
                        const hasLocation = cctvData.has_location !== false && cctvData.location && Array.isArray(cctvData.location) && cctvData.location.length === 2;
                        if (hasLocation) {
                            highlightAndZoomToLocation(cctvData.location, 'cctv', cctvData);
                        }
                    }
                }
                
                // Highlight active item
                document.querySelectorAll('.sidebar-list-item').forEach(i => {
                    if (i !== this) i.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
    }
    
    // Load CCTV details from API
    function loadCctvDetails(cctvId, itemElement) {
        const detailSection = itemElement.querySelector('.cctv-detail-section');
        if (!detailSection) return;
        
        // Check if already loaded
        if (detailSection.dataset.loaded === 'true') {
            return;
        }
        
        // Get CCTV data from itemElement data attributes
        const cctvName = itemElement.dataset.cctvName || '';
        const rtspUrl = itemElement.dataset.rtspUrl || '';
        
        // Show loading
        detailSection.innerHTML = `
            <div class="cctv-detail-loading">
                <i class="material-icons-outlined" style="font-size: 24px; margin-bottom: 8px; opacity: 0.7;">hourglass_empty</i>
                <div style="margin-top: 8px;">Memuat detail...</div>
            </div>
        `;
        
        // Fetch details
        const detailsUrl = `{{ url('cctv-data') }}/${cctvId}/details`;
        fetch(detailsUrl)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    detailSection.dataset.loaded = 'true';
                    // Pass CCTV name and rtsp_url to renderCctvDetails
                    renderCctvDetails(result.data, detailSection, cctvName, rtspUrl);
                } else {
                    detailSection.innerHTML = `
                        <div class="cctv-detail-error">
                            <i class="material-icons-outlined" style="font-size: 18px;">error_outline</i>
                            <span>Error: ${escapeHtml(result.error || 'Gagal memuat detail')}</span>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading CCTV details:', error);
                detailSection.innerHTML = `
                    <div class="cctv-detail-error">
                        <i class="material-icons-outlined" style="font-size: 18px;">error_outline</i>
                        <span>Error: Gagal memuat detail CCTV</span>
                    </div>
                `;
            });
    }
    
    // Render CCTV details
    function renderCctvDetails(data, container, cctvName = '', rtspUrl = '') {
        const { coverages, hazard_stats, pja_list, week_range } = data;
        
        let html = '';
        
        // Coverage Lokasi Section
        html += '<div class="cctv-detail-group">';
        html += '<div class="cctv-detail-group-title"><i class="material-icons-outlined">location_on</i> <span>Coverage Lokasi</span></div>';
        if (coverages && coverages.length > 0) {
            coverages.forEach(coverage => {
                html += `
                    <div class="cctv-coverage-item">
                        <div class="cctv-coverage-lokasi">${escapeHtml(coverage.coverage_lokasi || '-')}</div>
                        <div class="cctv-coverage-detail">${escapeHtml(coverage.coverage_detail_lokasi || '-')}</div>
                    </div>
                `;
            });
        } else {
            html += '<div class="cctv-no-data">Tidak ada data coverage</div>';
        }
        html += '</div>';
        
        // Hazard Inspection Statistics Section
        html += '<div class="cctv-detail-group">';
        html += '<div class="cctv-detail-group-title"><i class="material-icons-outlined">warning</i> <span>Inspeksi Hazard (Minggu Ini)</span></div>';
        if (week_range) {
            html += `<div style="font-size: 11px; color: #6b7280; margin-bottom: 10px; padding: 6px 10px; background: #f3f4f6; border-radius: 4px; display: inline-block;">📅 ${week_range.start} - ${week_range.end}</div>`;
        }
        if (hazard_stats && hazard_stats.length > 0) {
            hazard_stats.forEach(stat => {
                html += `
                    <div class="cctv-hazard-stat">
                        <div class="cctv-hazard-stat-header">${escapeHtml(stat.detail_lokasi || '-')}</div>
                        <div class="cctv-hazard-stat-count">Total: ${stat.total_count} inspeksi</div>
                    </div>
                `;
            });
        } else {
            html += '<div class="cctv-no-data">Tidak ada inspeksi hazard minggu ini</div>';
        }
        html += '</div>';
        
        // PJA Section
        html += '<div class="cctv-detail-group">';
        html += '<div class="cctv-detail-group-title"><i class="material-icons-outlined">person</i> <span>PJA Lokasi</span></div>';
        if (pja_list && Object.keys(pja_list).length > 0) {
            Object.keys(pja_list).forEach(detailLokasi => {
                const pjas = pja_list[detailLokasi];
                pjas.forEach(pja => {
                    html += `
                        <div class="cctv-pja-item">
                            <div class="cctv-pja-name">${escapeHtml(pja.nama_pja || '-')}</div>
                            <div class="cctv-pja-info">
                                ${escapeHtml(pja.employee_name || '-')} (${escapeHtml(pja.kode_sid || '-')})
                                ${pja.employee_email ? `<br>📧 ${escapeHtml(pja.employee_email)}` : ''}
                            </div>
                        </div>
                    `;
                });
            });
        } else {
            html += '<div class="cctv-no-data">Tidak ada data PJA</div>';
        }
        html += '</div>';
        
        // CCTV Stream Section (directly below PJA)
        const hasRtspStream = rtspUrl && rtspUrl.trim() !== '';
        if (hasRtspStream) {
            html += '<div class="cctv-detail-group">';
            html += '<div class="cctv-detail-group-title"><i class="material-icons-outlined">videocam</i> <span>CCTV Live Stream</span></div>';
            html += `<div class="cctv-stream-container" style="width: 100%; height: 200px; background-color: #000; position: relative; border-radius: 4px; overflow: hidden;" data-cctv-name="${escapeHtml(cctvName).replace(/"/g, '&quot;')}" data-rtsp-url="${escapeHtml(rtspUrl).replace(/"/g, '&quot;')}">`;
            html += '<iframe class="cctv-stream-iframe" style="width: 100%; height: 100%; border: none; display: none; background: #000;" allowfullscreen allow="autoplay; fullscreen"></iframe>';
            html += '<div class="cctv-stream-loading position-absolute top-50 start-50 translate-middle text-center text-white" style="display: block;">';
            html += '<div class="spinner-border spinner-border-sm text-primary" role="status">';
            html += '<span class="visually-hidden">Loading...</span>';
            html += '</div>';
            html += '<p class="mt-2 mb-0" style="font-size: 11px;">Memuat stream...</p>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
        }
        
        container.innerHTML = html;
        
        // Load stream directly if available
        if (hasRtspStream) {
            const streamContainer = container.querySelector('.cctv-stream-container');
            if (streamContainer) {
                loadCCTVStreamInline(streamContainer, cctvName, rtspUrl);
            }
        }
    }
    
    // Function to load CCTV stream inline in sidebar
    function loadCCTVStreamInline(container, cctvName, rtspUrl) {
        const streamFrame = container.querySelector('.cctv-stream-iframe');
        const streamLoading = container.querySelector('.cctv-stream-loading');
        
        if (!streamFrame || !streamLoading) return;
        
        // Build Python app URL
        const pythonAppBaseUrl = pythonAppUrl || 'http://localhost:5000';
        const pythonStreamUrl = `${pythonAppBaseUrl}?cctv=${encodeURIComponent(cctvName)}&rtsp=${encodeURIComponent(rtspUrl || '')}`;
        
        // Set iframe source
        streamFrame.src = pythonStreamUrl;
        
        // Handle iframe load
        streamFrame.onload = function() {
            if (streamLoading) {
                streamLoading.style.display = 'none';
            }
            streamFrame.style.display = 'block';
        };
        
        // Handle iframe error
        streamFrame.onerror = function() {
            if (streamLoading) {
                streamLoading.style.display = 'block';
                streamLoading.innerHTML = `
                    <div class="text-center text-white">
                        <i class="material-icons-outlined" style="font-size: 24px; color: #ef4444;">error_outline</i>
                        <p class="mt-1 mb-0" style="font-size: 11px;">Gagal memuat stream</p>
                    </div>
                `;
            }
            streamFrame.style.display = 'none';
        };
        
        // Timeout check
        setTimeout(function() {
            if (streamFrame.style.display === 'none' && streamLoading && streamLoading.style.display === 'block') {
                try {
                    const frameDoc = streamFrame.contentDocument || streamFrame.contentWindow.document;
                    if (streamLoading) streamLoading.style.display = 'none';
                    streamFrame.style.display = 'block';
                } catch (e) {
                    // Cross-origin is expected, assume it's loading
                    if (streamLoading) streamLoading.style.display = 'none';
                    streamFrame.style.display = 'block';
                }
            }
        }, 3000);
    }
    
    // Load hazard status for multiple CCTV
    function loadCctvHazardStatus(cctvIds) {
        if (!cctvIds || cctvIds.length === 0) return;
        
        const statusUrl = `{{ url('cctv-data/hazard-status') }}?ids=${cctvIds.join(',')}`;
        
        fetch(statusUrl)
            .then(response => response.json())
            .then(result => {
                if (result.success && result.data) {
                    // Update status for each CCTV item
                    Object.keys(result.data).forEach(cctvId => {
                        const status = result.data[cctvId];
                        const item = document.querySelector(`.sidebar-list-item[data-id="${cctvId}"]`);
                        if (item) {
                            const statusIcon = item.querySelector('.cctv-hazard-status-icon');
                            const hasHazard = status.has_hazard_inspection;
                            
                            // Update data attribute
                            item.setAttribute('data-hazard-status', hasHazard ? 'has' : 'no');
                            
                            // Update classes
                            item.classList.remove('no-hazard-inspection', 'has-hazard-inspection');
                            if (hasHazard) {
                                item.classList.add('has-hazard-inspection');
                            } else {
                                item.classList.add('no-hazard-inspection');
                            }
                            
                            // Update icon
                            if (statusIcon) {
                                statusIcon.classList.remove('loading', 'has-hazard', 'no-hazard');
                                if (hasHazard) {
                                    statusIcon.classList.add('has-hazard');
                                    statusIcon.innerHTML = '<i class="material-icons-outlined" style="font-size: 14px;">check_circle</i>';
                                    statusIcon.title = `Ada ${status.total_count} inspeksi hazard minggu ini`;
                                } else {
                                    statusIcon.classList.add('no-hazard');
                                    statusIcon.innerHTML = '<i class="material-icons-outlined" style="font-size: 14px;">warning</i>';
                                    statusIcon.title = 'Belum ada inspeksi hazard minggu ini';
                                }
                            }
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error loading CCTV hazard status:', error);
                // Update all items to show error state
                cctvIds.forEach(cctvId => {
                    const item = document.querySelector(`.sidebar-list-item[data-id="${cctvId}"]`);
                    if (item) {
                        const statusIcon = item.querySelector('.cctv-hazard-status-icon');
                        if (statusIcon) {
                            statusIcon.classList.remove('loading', 'has-hazard', 'no-hazard');
                            statusIcon.classList.add('no-hazard');
                            statusIcon.innerHTML = '<i class="material-icons-outlined" style="font-size: 14px;">error_outline</i>';
                            statusIcon.title = 'Error memuat status';
                        }
                    }
                });
            });
    }
    
    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Render SAP list
    function renderSapList(data) {
        const container = document.getElementById('sapList');
        if (!container) return;
        
        if (!data || data.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="material-icons-outlined">assignment</i>
                    <p>Tidak ada data SAP</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = data.map((sap, index) => {
            const name = sap.jenis_laporan || sap.aktivitas_pekerjaan || sap.task_number || `SAP ${sap.id}`;
            const taskNumber = sap.task_number || '';
            const lokasi = sap.lokasi || sap.detail_lokasi || '';
            const firstLetter = getFirstLetter(name);
            const avatarColor = '#3b82f6'; // Blue untuk SAP
            const timestamp = formatTimestamp(sap.tanggal_pelaporan || sap.detected_at);
            
            return `
                <div class="sidebar-list-item" data-type="sap" data-id="${sap.id}" data-index="${index}">
                    <div class="list-item-avatar" style="background-color: ${avatarColor};">
                        ${firstLetter}
                    </div>
                    <div class="list-item-content">
                        <div class="list-item-title">${name}</div>
                        <div class="list-item-subtitle">${taskNumber ? `Task: ${taskNumber}` : ''}${lokasi ? ` - ${lokasi}` : ''}</div>
                        ${timestamp ? `<div class="list-item-time">${timestamp}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
        
        // Add click handlers
        container.querySelectorAll('.sidebar-list-item').forEach(item => {
            item.addEventListener('click', function() {
                const sapId = this.dataset.id;
                const sapData = data.find(s => s.id == sapId);
                if (sapData) {
                    // Highlight item di sidebar
                    document.querySelectorAll('.sidebar-list-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Jika punya koordinat, zoom ke lokasi
                    if (sapData.location && sapData.location.lat && sapData.location.lng) {
                        highlightAndZoomToLocation(sapData.location, 'sap', sapData);
                    }
                    
                    // Buka modal detail SAP
                    if (sapData.task_number) {
                        openSapDetailModal(sapData.task_number);
                    } else {
                        // Jika tidak ada task_number, gunakan data langsung
                        const modal = new bootstrap.Modal(document.getElementById('sapDetailModal'));
                        modal.show();
                        populateSapDetailModal(sapData);
                    }
                }
            });
        });
    }
    
    // Render Insiden list
    function renderInsidenList(data) {
        const container = document.getElementById('insidenList');
        if (!container) return;
        
        if (!data || data.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="material-icons-outlined">report_problem</i>
                    <p>Tidak ada data Insiden</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = data.map((insiden, index) => {
            const name = insiden.no_kecelakaan || `Insiden ${index + 1}`;
            const lokasi = insiden.lokasi || '';
            const firstLetter = getFirstLetter(name);
            const avatarColor = '#f97316';
            const timestamp = formatTimestamp(insiden.tanggal);
            
            return `
                <div class="sidebar-list-item" data-type="insiden" data-id="${insiden.no_kecelakaan}" data-index="${index}">
                    <div class="list-item-avatar" style="background-color: ${avatarColor};">
                        ${firstLetter}
                    </div>
                    <div class="list-item-content">
                        <div class="list-item-title">${name}</div>
                        <div class="list-item-subtitle">${lokasi || 'Lokasi tidak diketahui'}</div>
                        ${timestamp ? `<div class="list-item-time">${timestamp}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
        
        // Add click handlers
        container.querySelectorAll('.sidebar-list-item').forEach(item => {
            item.addEventListener('click', function() {
                const insidenId = this.dataset.id;
                const insidenData = data.find(i => i.no_kecelakaan == insidenId);
                if (insidenData && insidenData.latitude && insidenData.longitude) {
                    highlightAndZoomToLocation([insidenData.longitude, insidenData.latitude], 'insiden', insidenData);
                }
            });
        });
    }
    
    // Render Unit list
    function renderUnitList(data) {
        const container = document.getElementById('unitList');
        if (!container) {
            console.warn('unitList container not found');
            return;
        }
        
        console.log('Rendering unit list, data count:', data ? data.length : 0, 'data:', data);
        
        if (!data || data.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="material-icons-outlined">directions_car</i>
                    <p>Tidak ada data Unit</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = data.map((unit, index) => {
            const name = unit.unit_id || unit.unit_name || unit.vehicle_name || unit.vehicle_number || unit.integration_id || `Unit ${index + 1}`;
            const id = unit.integration_id || unit.unit_id || '';
            const vehicleNumber = unit.vehicle_number || '';
            const vehicleType = unit.vehicle_type || '';
            const firstLetter = getFirstLetter(name);
            const avatarColor = getAvatarColor(firstLetter);
            const timestamp = formatTimestamp(unit.timestamp || unit.updated_at || unit.last_update);
            
            return `
                <div class="sidebar-list-item" data-type="unit" data-id="${id || unit.unit_id || unit.integration_id}" data-index="${index}">
                    <div class="list-item-avatar" style="background-color: ${avatarColor};">
                        ${firstLetter}
                    </div>
                    <div class="list-item-content">
                        <div class="list-item-title">${name}</div>
                        <div class="list-item-subtitle">
                            ${vehicleNumber ? `No: ${vehicleNumber}` : ''} 
                            ${vehicleType ? `- ${vehicleType}` : ''}
                            ${id ? ` (ID: ${id})` : ''}
                        </div>
                        ${timestamp ? `<div class="list-item-time">${timestamp}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
        
        // Add click handlers
        container.querySelectorAll('.sidebar-list-item').forEach(item => {
            item.addEventListener('click', function() {
                const unitId = this.dataset.id;
                const unitData = data.find(u => {
                    const uId = u.integration_id || u.unit_id || u.id;
                    return uId == unitId;
                });
                if (unitData) {
                    // Highlight item di sidebar
                    document.querySelectorAll('.sidebar-list-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Jika punya koordinat, zoom ke lokasi
                    if (unitData.latitude && unitData.longitude && unitData.latitude != 0 && unitData.longitude != 0) {
                        highlightAndZoomToLocation({ lat: unitData.latitude, lng: unitData.longitude }, 'unit', unitData);
                    }
                }
            });
        });
    }
    
    // Initialize Control Room data - group CCTV by control_room
    function initializeControlRoomData() {
        if (!cctvLocations || cctvLocations.length === 0) {
            filteredSidebarData.controlroom = [];
            originalControlRoomData = [];
            return;
        }
        
        // Group CCTV by control_room (filter out empty/null control_room)
        const controlRoomMap = {};
        
        cctvLocations.forEach(cctv => {
            const controlRoom = (cctv.control_room && cctv.control_room.trim() !== '') 
                ? cctv.control_room.trim() 
                : null;
            
            // Skip if control_room is null or empty
            if (!controlRoom) return;
            
            if (!controlRoomMap[controlRoom]) {
                controlRoomMap[controlRoom] = {
                    name: controlRoom,
                    cctv_list: []
                };
            }
            
            controlRoomMap[controlRoom].cctv_list.push(cctv);
        });
        
        // Convert to array and sort by name
        const controlRoomData = Object.values(controlRoomMap)
            .sort((a, b) => a.name.localeCompare(b.name));
        
        // Store original data for filtering
        originalControlRoomData = JSON.parse(JSON.stringify(controlRoomData));
        filteredSidebarData.controlroom = controlRoomData;
    }
    
    // Render Control Room list
    function renderControlRoomList(data) {
        const container = document.getElementById('controlroomList');
        if (!container) return;
        
        if (!data || data.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="material-icons-outlined">meeting_room</i>
                    <p>Tidak ada data Control Room</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = data.map((controlRoom, index) => {
            const name = controlRoom.name || 'Unknown';
            const cctvCount = controlRoom.cctv_list ? controlRoom.cctv_list.length : 0;
            const firstLetter = getFirstLetter(name);
            const avatarColor = getAvatarColor(firstLetter);
            
            // Build CCTV list HTML
            const cctvListHtml = controlRoom.cctv_list ? controlRoom.cctv_list.map((cctv, cctvIndex) => {
                const cctvName = cctv.name || cctv.nama_cctv || cctv.no_cctv || `CCTV ${cctv.id}`;
                const cctvId = cctv.no_cctv || cctv.nomor_cctv || cctv.id || '';
                const cctvKondisi = cctv.kondisi || cctv.status || '';
                const cctvLinkAkses = cctv.link_akses || cctv.externalUrl || '';
                const cctvLokasi = cctv.lokasi_pemasangan || cctv.coverage_detail_lokasi || '';
                const kategoriArea = cctv.kategori_area_tercapture || '';
                
                let kondisiBadge = '';
                if (cctvKondisi === 'Baik') {
                    kondisiBadge = '<span class="badge bg-success rounded-pill ms-2" style="font-size: 10px; padding: 2px 8px; font-weight: 500;">Baik</span>';
                } else if (cctvKondisi === 'Rusak') {
                    kondisiBadge = '<span class="badge bg-danger rounded-pill ms-2" style="font-size: 10px; padding: 2px 8px; font-weight: 500;">Rusak</span>';
                }
                
                return `
                    <div class="controlroom-cctv-item" data-cctv-id="${cctv.id}" data-index="${cctvIndex}">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;">
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px; margin-bottom: 4px;">
                                    <span style="font-size: 13px; font-weight: 500; color: #111827; line-height: 1.4;">
                                        ${escapeHtml(cctvName)}
                                    </span>
                                    ${kondisiBadge}
                                </div>
                                ${cctvId ? `
                                    <div style="font-size: 11px; color: #6b7280; margin-bottom: 2px; display: flex; align-items: center; gap: 6px;">
                                        <span>${escapeHtml(cctvId)}</span>
                                        ${cctvLinkAkses ? `
                                            <button class="btn btn-link p-0" style="color: #3b82f6; text-decoration: none; min-width: auto; padding: 0; line-height: 1; height: auto;" 
                                                    onclick="event.stopPropagation(); window.open('${cctvLinkAkses}', '_blank');" 
                                                    title="Buka Link Akses">
                                                <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">open_in_new</i>
                                            </button>
                                        ` : ''}
                                    </div>
                                ` : ''}
                                ${cctvLokasi ? `
                                    <div style="font-size: 11px; color: #9ca3af; line-height: 1.4; margin-top: 2px;">
                                        ${escapeHtml(cctvLokasi)}
                                    </div>
                                ` : ''}
                                ${kategoriArea ? `
                                    <div style="font-size: 10px; color: #d1d5db; margin-top: 4px; font-style: italic;">
                                        ${escapeHtml(kategoriArea)}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }).join('') : '';
            
            return `
                <div class="sidebar-list-item controlroom-item" data-type="controlroom" data-controlroom="${escapeHtml(name)}" data-index="${index}">
                    <div class="sidebar-list-item-header">
                        <div class="list-item-avatar" style="background-color: ${avatarColor};">
                            ${firstLetter}
                        </div>
                        <div class="list-item-content" style="flex: 1;">
                            <div class="list-item-title">${escapeHtml(name)}</div>
                            <div class="list-item-subtitle">
                                <span style="font-weight: 500; color: #3b82f6;">${cctvCount}</span> CCTV
                            </div>
                        </div>
                        <i class="material-icons-outlined list-item-expand-icon">expand_more</i>
                    </div>
                    <div class="controlroom-cctv-list">
                        ${cctvListHtml || '<div style="padding: 16px; text-align: center; color: #9ca3af; font-size: 12px;">Tidak ada CCTV</div>'}
                    </div>
                </div>
            `;
        }).join('');
        
        // Add click handlers for expand/collapse
        container.querySelectorAll('.controlroom-item').forEach(item => {
            const header = item.querySelector('.sidebar-list-item-header');
            const cctvList = item.querySelector('.controlroom-cctv-list');
            const expandIcon = item.querySelector('.list-item-expand-icon');
            
            header.addEventListener('click', function(e) {
                e.stopPropagation();
                
                const isExpanded = item.classList.contains('expanded');
                
                if (isExpanded) {
                    // Collapse
                    item.classList.remove('expanded');
                } else {
                    // Expand
                    item.classList.add('expanded');
                }
            });
            
            // Add click handlers for CCTV items
            item.querySelectorAll('.controlroom-cctv-item').forEach(cctvItem => {
                cctvItem.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const cctvId = this.dataset.cctvId;
                    const cctvData = cctvLocations.find(c => c.id == cctvId);
                    
                    if (cctvData) {
                        // Remove active class from all items
                        document.querySelectorAll('.controlroom-cctv-item').forEach(i => i.classList.remove('active'));
                        // Add active class to clicked item
                        this.classList.add('active');
                        
                        // Scroll into view
                        this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        // Jika CCTV punya koordinat, zoom ke lokasi
                        if (cctvData.has_location !== false && cctvData.location && Array.isArray(cctvData.location) && cctvData.location.length === 2) {
                            highlightAndZoomToLocation(cctvData.location, 'cctv', cctvData);
                        }
                    }
                });
            });
        });
    }
    
    // Check if user is admin (from backend)
    const isUserAdmin = @json(auth()->user() && (method_exists(auth()->user(), 'isAdmin') ? auth()->user()->isAdmin() : (isset(auth()->user()->role) && (auth()->user()->role === 'admin' || auth()->user()->role === 'administrator'))));
    
    // Flag untuk mencegah load history berulang
    let gpsHistoryLoaded = false;
    
    // Render GPS Orang list
    function renderGpsList(data) {
        const container = document.getElementById('gpsList');
        if (!container) return;
        
        // Jika user bukan admin, tampilkan history lokasi (hanya sekali)
        if (!isUserAdmin && !gpsHistoryLoaded) {
            gpsHistoryLoaded = true;
            loadUserGpsHistory();
            return;
        }
        
        if (!data || data.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="material-icons-outlined">person_pin</i>
                    <p>Tidak ada data GPS Orang hari ini</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = data.map((user, index) => {
            const name = user.fullname || user.npk || `User ${index + 1}`;
            const npk = user.npk || '';
            const position = user.functional_position || user.structural_position || '';
            const department = user.department_name || user.division_name || '';
            const firstLetter = getFirstLetter(name);
            const avatarColor = getAvatarColor(firstLetter);
            const timestamp = formatTimestamp(user.gps_updated_at || user.gps_created_at);
            const battery = user.battery !== null && user.battery !== undefined ? user.battery : null;
            const batteryColor = battery < 20 ? '#ef4444' : battery < 50 ? '#f59e0b' : '#10b981';
            
            return `
                <div class="sidebar-list-item" data-type="gps" data-id="${user.user_id || user.id}" data-index="${index}">
                    <div class="list-item-avatar" style="background-color: ${avatarColor};">
                        ${firstLetter}
                    </div>
                    <div class="list-item-content">
                        <div class="list-item-title">${name}</div>
                        <div class="list-item-subtitle">
                            ${npk ? `SID: ${npk}` : ''} 
                            ${position ? `- ${position}` : ''}
                            ${department ? ` (${department})` : ''}
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px; font-size: 11px; color: #6b7280;">
                            ${battery !== null ? `
                                <span style="display: flex; align-items: center; gap: 4px;">
                                    <i class="material-icons-outlined" style="font-size: 14px;">battery_charging_full</i>
                                    <span style="color: ${batteryColor};">${battery}%</span>
                                </span>
                            ` : ''}
                            ${timestamp ? `<span>${timestamp}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        // Add click handlers
        container.querySelectorAll('.sidebar-list-item').forEach(item => {
            item.addEventListener('click', function() {
                const userId = this.dataset.id;
                const userData = data.find(u => (u.user_id || u.id) == userId);
                if (userData) {
                    // Highlight item di sidebar
                    document.querySelectorAll('.sidebar-list-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Jika punya koordinat, zoom ke lokasi
                    if (userData.latitude && userData.longitude) {
                        highlightAndZoomToLocation({ lat: userData.latitude, lng: userData.longitude }, 'gps', userData);
                    }
                }
            });
        });
    }
    
    // Function to load user GPS history (for non-admin users)
    function loadUserGpsHistory() {
        const container = document.getElementById('gpsList');
        if (!container) return;
        
        // Show loading state
        container.innerHTML = `
            <div class="empty-state">
                <div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p style="margin-top: 16px;">Memuat history lokasi...</p>
            </div>
        `;
        
        fetch('{{ route("maps.api.user-gps-history") }}?days=7')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.history) {
                    // Simpan history ke filteredSidebarData
                    filteredSidebarData.gpsHistory = data.history;
                    renderGpsHistoryList(data.history);
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="material-icons-outlined">history</i>
                            <p>Tidak ada history lokasi</p>
                            ${data.error ? `<p style="font-size: 11px; color: #ef4444; margin-top: 8px;">${data.error}</p>` : ''}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading GPS history:', error);
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="material-icons-outlined">error_outline</i>
                        <p>Gagal memuat history lokasi</p>
                        <p style="font-size: 11px; color: #ef4444; margin-top: 8px;">Silakan refresh halaman</p>
                    </div>
                `;
            });
    }
    
    // Function to render GPS history list with collapsible
    function renderGpsHistoryList(history) {
        const container = document.getElementById('gpsList');
        if (!container) return;
        
        if (!history || history.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="material-icons-outlined">history</i>
                    <p>Tidak ada history lokasi</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = history.map((location, index) => {
            const visitCount = location.visit_count || 0;
            const firstVisit = location.first_visit ? formatDateWIB(location.first_visit) : 'N/A';
            const lastVisit = location.last_visit ? formatDateWIB(location.last_visit) : 'N/A';
            const locationId = `history-loc-${index}`;
            const detailId = `history-detail-${index}`;
            
            return `
                <div class="sidebar-list-item history-location-item" data-location-key="${location.location_key}" data-index="${index}">
                    <div class="d-flex align-items-start gap-2 w-100" style="cursor: pointer;" onclick="toggleHistoryDetail('${detailId}', '${locationId}')">
                        <div class="list-item-avatar" style="background-color: #3b82f6; min-width: 40px; width: 40px; height: 40px;">
                            <i class="material-icons-outlined" style="color: white; font-size: 20px;">location_on</i>
                        </div>
                        <div class="list-item-content flex-grow-1">
                            <div class="list-item-title">Lokasi ${index + 1}</div>
                            <div class="list-item-subtitle">
                                Kunjungan: <strong>${visitCount}x</strong>
                            </div>
                            <div style="font-size: 11px; color: #6b7280; margin-top: 4px;">
                                <div>Pertama: ${firstVisit.split(',')[0]}</div>
                                <div>Terakhir: ${lastVisit.split(',')[0]}</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="material-icons-outlined history-toggle-icon" id="${locationId}" style="transition: transform 0.3s;">expand_more</i>
                        </div>
                    </div>
                    <div class="history-detail-content" id="${detailId}" style="display: none; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb;">
                        <div class="text-center py-2">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <p class="mt-2 mb-0" style="font-size: 12px; color: #6b7280;">Memuat detail lokasi...</p>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        // Add click handler untuk zoom ke lokasi
        container.querySelectorAll('.history-location-item').forEach(item => {
            const locationData = history[parseInt(item.dataset.index)];
            if (locationData && locationData.latitude && locationData.longitude) {
                item.style.cursor = 'pointer';
                item.addEventListener('click', function(e) {
                    // Jangan trigger jika klik pada toggle icon atau detail content
                    if (e.target.closest('.history-detail-content') || e.target.closest('.history-toggle-icon')) {
                        return;
                    }
                    
                    highlightAndZoomToLocation(
                        { lat: locationData.latitude, lng: locationData.longitude }, 
                        'gps', 
                        locationData
                    );
                });
            }
        });
    }
    
    // Function to toggle history detail
    window.toggleHistoryDetail = function(detailId, iconId) {
        const detailContent = document.getElementById(detailId);
        const toggleIcon = document.getElementById(iconId);
        
        if (!detailContent || !toggleIcon) return;
        
        const isExpanded = detailContent.style.display !== 'none';
        
        if (isExpanded) {
            // Collapse
            detailContent.style.display = 'none';
            toggleIcon.style.transform = 'rotate(0deg)';
            toggleIcon.textContent = 'expand_more';
        } else {
            // Expand
            detailContent.style.display = 'block';
            toggleIcon.style.transform = 'rotate(180deg)';
            toggleIcon.textContent = 'expand_less';
            
            // Load detail lokasi jika belum di-load
            const locationItem = detailContent.closest('.history-location-item');
            if (locationItem && !locationItem.dataset.detailLoaded) {
                loadHistoryLocationDetail(locationItem, detailContent);
            }
        }
    };
    
    // Function to load detail for a history location
    function loadHistoryLocationDetail(locationItem, detailContainer) {
        const locationIndex = locationItem.dataset.index;
        const historyData = filteredSidebarData.gpsHistory || [];
        const location = historyData[locationIndex];
        
        if (!location || !location.latitude || !location.longitude) {
            detailContainer.innerHTML = `
                <div class="text-center py-2">
                    <p style="font-size: 12px; color: #ef4444;">Data lokasi tidak valid</p>
                </div>
            `;
            return;
        }
        
        // Load location details
        fetch(`{{ route('maps.api.gps-user-location-details') }}?latitude=${location.latitude}&longitude=${location.longitude}${location.location_id ? '&location_id=' + location.location_id : ''}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const locationData = data.data;
                    const workAreaName = locationData.work_area ? locationData.work_area.name : 'Tidak diketahui';
                    const sapCount = locationData.sap_count || 0;
                    const sapOpenCount = locationData.sap_open_count || 0;
                    const cctvCount = locationData.cctv_count || 0;
                    const pjaCount = locationData.pja_count || 0;
                    
                    // Get visit details
                    const visits = location.visits || [];
                    const visitList = visits.slice(0, 10).map(visit => {
                        const visitDate = visit.date ? formatDateWIB(visit.date) : 'N/A';
                        return `
                            <div style="padding: 8px; border-bottom: 1px solid #f3f4f6; font-size: 12px;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span>${visitDate.split(',')[0]}</span>
                                    <span style="color: #6b7280;">${visitDate.split(',')[1] || ''}</span>
                                </div>
                            </div>
                        `;
                    }).join('');
                    
                    detailContainer.innerHTML = `
                        <div style="background-color: #f9fafb; padding: 12px; border-radius: 6px; margin-bottom: 12px;">
                            <p style="margin: 0 0 8px 0; font-size: 13px; font-weight: 600;">
                                <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">location_on</i>
                                Area Kerja: ${workAreaName}
                            </p>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px;">
                                <div>
                                    <span style="color: #6b7280;">SAP:</span>
                                    <strong style="color: #ef4444;">${sapCount}</strong>
                                </div>
                                <div>
                                    <span style="color: #6b7280;">SAP Open:</span>
                                    <strong style="color: #dc2626;">${sapOpenCount}</strong>
                                </div>
                                <div>
                                    <span style="color: #6b7280;">CCTV:</span>
                                    <strong style="color: #3b82f6;">${cctvCount}</strong>
                                </div>
                                <div>
                                    <span style="color: #6b7280;">PJA:</span>
                                    <strong style="color: #8b5cf6;">${pjaCount}</strong>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 12px;">
                            <p style="margin: 0 0 8px 0; font-size: 13px; font-weight: 600;">
                                <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">schedule</i>
                                Riwayat Kunjungan (${visits.length})
                            </p>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 6px;">
                                ${visitList || '<div style="padding: 12px; text-align: center; color: #6b7280; font-size: 12px;">Tidak ada data kunjungan</div>'}
                            </div>
                        </div>
                        
                        <button class="btn btn-sm btn-primary w-100" onclick="showHistoryLocationDetailModal(${JSON.stringify(location).replace(/"/g, '&quot;')}, ${JSON.stringify(locationData).replace(/"/g, '&quot;')})">
                            <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">info</i>
                            Lihat Detail Lengkap
                        </button>
                    `;
                    
                    locationItem.dataset.detailLoaded = 'true';
                } else {
                    detailContainer.innerHTML = `
                        <div class="text-center py-2">
                            <p style="font-size: 12px; color: #ef4444;">Gagal memuat detail lokasi</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading location detail:', error);
                detailContainer.innerHTML = `
                    <div class="text-center py-2">
                        <p style="font-size: 12px; color: #ef4444;">Error: ${error.message}</p>
                    </div>
                `;
            });
    }
    
    // Function to show history location detail modal
    window.showHistoryLocationDetailModal = function(location, locationData) {
        // Reuse the same modal function as current location
        showGpsUserDetailModal(
            {
                fullname: location.visits && location.visits[0] ? location.visits[0].nama : 'History Lokasi',
                latitude: location.latitude,
                longitude: location.longitude
            },
            locationData
        );
    };
    
    // Load PJA data from API
    function loadPjaData() {
        const container = document.getElementById('pjaList');
        if (!container) return;
        
        // Show loading state
        container.innerHTML = `
            <div class="empty-state">
                <div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p style="margin-top: 16px;">Memuat data PJA...</p>
            </div>
        `;
        
        // Update tab count to show loading
        const pjaTabCount = document.getElementById('pjaTabCount');
        if (pjaTabCount) pjaTabCount.textContent = '...';
        
        fetch('{{ route("maps.api.pja-data") }}')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.data) {
                    originalPjaData = data.data;
                    filteredSidebarData.pja = data.data;
                    updateTabCounts();
                    renderPjaList(filteredSidebarData.pja);
                } else {
                    filteredSidebarData.pja = [];
                    updateTabCounts();
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="material-icons-outlined">description</i>
                            <p>Tidak ada data PJA</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading PJA data:', error);
                filteredSidebarData.pja = [];
                updateTabCounts();
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="material-icons-outlined">error_outline</i>
                        <p>Gagal memuat data PJA</p>
                        <small style="color: #9ca3af;">${error.message}</small>
                    </div>
                `;
            });
    }
    
    // Render PJA list
    function renderPjaList(data) {
        const container = document.getElementById('pjaList');
        if (!container) return;
        
        if (!data || data.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="material-icons-outlined">description</i>
                    <p>Tidak ada data PJA</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = data.map((pja, index) => {
            const namaPja = pja.nama_pja || `PJA ${pja.pja_id || index + 1}`;
            const site = pja.site || '';
            const lokasi = pja.lokasi || '';
            const detailLokasi = pja.detail_lokasi || '';
            const pjaType = pja.pja_type_name || '';
            const pjaCategory = pja.pja_category_name || '';
            const pjaLayer = pja.pja_layer || '';
            const employeeName = pja.employee_name || '';
            const nik = pja.nik || '';
            const kodeSid = pja.kode_sid || '';
            const kategoriPja = pja.kategori_pja || '';
            const firstLetter = getFirstLetter(namaPja);
            const avatarColor = '#8b5cf6'; // Purple untuk PJA
            
            return `
                <div class="sidebar-list-item" data-type="pja" data-id="${pja.pja_id || index}" data-index="${index}">
                    <div class="list-item-avatar" style="background-color: ${avatarColor};">
                        ${firstLetter}
                    </div>
                    <div class="list-item-content">
                        <div class="list-item-title">${namaPja}</div>
                        <div class="list-item-subtitle">
                            ${site ? `Site: ${site}` : ''} 
                            ${lokasi ? `- ${lokasi}` : ''}
                            ${detailLokasi ? ` (${detailLokasi})` : ''}
                        </div>
                        <div style="font-size: 11px; color: #6b7280; margin-top: 4px;">
                            ${pjaType ? `<span>Type: ${pjaType}</span>` : ''}
                            ${pjaCategory ? ` | Category: ${pjaCategory}` : ''}
                            ${pjaLayer ? ` | Layer: ${pjaLayer}` : ''}
                        </div>
                        ${employeeName ? `
                            <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">
                                ${employeeName} ${nik ? `(${nik})` : ''} ${kodeSid ? `- ${kodeSid}` : ''}
                            </div>
                        ` : ''}
                        ${kategoriPja ? `
                            <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">
                                Kategori: ${kategoriPja}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }).join('');
        
        // Add click handlers
        container.querySelectorAll('.sidebar-list-item').forEach(item => {
            item.addEventListener('click', function() {
                const pjaId = this.dataset.id;
                const pjaData = data.find(p => (p.pja_id || p.id) == pjaId);
                if (pjaData) {
                    // Highlight item di sidebar
                    document.querySelectorAll('.sidebar-list-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Log PJA data for debugging (can be extended to show modal or zoom to location)
                    console.log('PJA Data:', pjaData);
                }
            });
        });
    }
    
    // Load evaluation summary
    function loadEvaluationSummary(type, idLokasi, lokasiName, nomorCctv, cctvName) {
        // Switch to evaluasi tab
        currentSidebarTab = 'evaluasi';
        document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
        const evaluasiTab = document.querySelector('.sidebar-tab[data-tab="evaluasi"]');
        if (evaluasiTab) {
            evaluasiTab.classList.add('active');
        }
        renderSidebarTab('evaluasi');
        
        // Show loading state
        const evaluasiContent = document.getElementById('evaluasiContent');
        if (evaluasiContent) {
            evaluasiContent.innerHTML = `
                <div style="text-align: center; padding: 40px 20px;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p style="margin-top: 16px; color: #6b7280;">Memuat data evaluasi...</p>
                </div>
            `;
        }
        
        // Prepare request data
        const requestData = {
            type: type,
            id_lokasi: idLokasi || null,
            lokasi_name: lokasiName || null,
            nomor_cctv: nomorCctv || null,
            cctv_name: cctvName || null
        };
        
        // Make API call
        fetch('{{ route("maps.api.evaluation-summary") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderEvaluationSummary(data.data);
            } else {
                throw new Error(data.message || 'Error loading evaluation summary');
            }
        })
        .catch(error => {
            console.error('Error loading evaluation summary:', error);
            if (evaluasiContent) {
                evaluasiContent.innerHTML = `
                    <div style="text-align: center; padding: 40px 20px; color: #ef4444;">
                        <i class="material-icons-outlined" style="font-size: 48px; margin-bottom: 12px;">error_outline</i>
                        <p style="margin: 0; font-size: 14px;">Error: ${error.message || 'Gagal memuat data evaluasi'}</p>
                    </div>
                `;
            }
        });
    }
    
    // Render evaluation summary
    function renderEvaluationSummary(summary) {
        const evaluasiContent = document.getElementById('evaluasiContent');
        if (!evaluasiContent) return;
        
        const areaName = summary.area_name || 'N/A';
        const areaType = summary.area_type === 'area_kerja' ? 'Area Kerja' : summary.area_type === 'area_cctv' ? 'Area CCTV' : 'Area';
        const cctvList = summary.cctv_list || [];
        const today = new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        
        // Build CCTV list HTML
        let cctvListHtml = '';
        if (cctvList.length > 0) {
            cctvListHtml = cctvList.map(cctv => `
                <div style="padding: 10px; background: #f9fafb; border-radius: 6px; margin-bottom: 8px; border-left: 3px solid #3b82f6;">
                    <div style="font-weight: 600; font-size: 13px; color: #111827; margin-bottom: 4px;">
                        ${cctv.nama_cctv || cctv.no_cctv || 'CCTV'}
                    </div>
                    <div style="font-size: 11px; color: #6b7280;">
                        ${cctv.no_cctv ? `No: ${cctv.no_cctv}` : ''} ${cctv.lokasi ? `| ${cctv.lokasi}` : ''}
                    </div>
                </div>
            `).join('');
        } else {
            cctvListHtml = '<p style="margin: 0; font-size: 12px; color: #9ca3af; text-align: center; padding: 20px;">Tidak ada CCTV tercover</p>';
        }
        
        evaluasiContent.innerHTML = `
            <div style="padding: 16px;">
                <div style="margin-bottom: 20px;">
                    <h6 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: #111827;">${areaType}</h6>
                    <p style="margin: 0; font-size: 14px; color: #6b7280;">${areaName}</p>
                    <p style="margin: 4px 0 0 0; font-size: 12px; color: #9ca3af;">${today}</p>
                </div>
                
                <!-- CCTV Tercover -->
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                        <i class="material-icons-outlined" style="font-size: 20px; color: #6366f1;">videocam</i>
                        <h6 style="margin: 0; font-size: 14px; font-weight: 600; color: #111827;">CCTV Tercover (${cctvList.length})</h6>
                    </div>
                    <div style="max-height: 200px; overflow-y: auto;">
                        ${cctvListHtml}
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div style="display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 20px;">
                    <!-- Inspeksi Hari Ini -->
                    <div style="background: #dbeafe; border: 1px solid #93c5fd; border-radius: 8px; padding: 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="material-icons-outlined" style="font-size: 24px; color: #3b82f6;">assignment</i>
                                <span style="font-size: 14px; font-weight: 600; color: #1e40af;">Inspeksi</span>
                            </div>
                            <span style="font-size: 24px; font-weight: 700; color: #1e40af;">${summary.inspeksi_count || 0}</span>
                        </div>
                        <p style="margin: 0; font-size: 12px; color: #1e3a8a;">Jumlah inspeksi hari ini</p>
                    </div>
                    
                    <!-- Hazard Hari Ini -->
                    <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="material-icons-outlined" style="font-size: 24px; color: #f59e0b;">warning</i>
                                <span style="font-size: 14px; font-weight: 600; color: #92400e;">Hazard</span>
                            </div>
                            <span style="font-size: 24px; font-weight: 700; color: #92400e;">${summary.hazard_count || 0}</span>
                        </div>
                        <p style="margin: 0; font-size: 12px; color: #78350f;">Jumlah hazard hari ini</p>
                    </div>
                    
                    <!-- Coaching Hari Ini -->
                    <div style="background: #e0e7ff; border: 1px solid #a5b4fc; border-radius: 8px; padding: 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="material-icons-outlined" style="font-size: 24px; color: #6366f1;">school</i>
                                <span style="font-size: 14px; font-weight: 600; color: #4338ca;">Coaching</span>
                            </div>
                            <span style="font-size: 24px; font-weight: 700; color: #4338ca;">${summary.coaching_count || 0}</span>
                        </div>
                        <p style="margin: 0; font-size: 12px; color: #3730a3;">Jumlah coaching hari ini</p>
                    </div>
                    
                    <!-- Observasi Hari Ini -->
                    <div style="background: #d1fae5; border: 1px solid #6ee7b7; border-radius: 8px; padding: 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="material-icons-outlined" style="font-size: 24px; color: #10b981;">visibility</i>
                                <span style="font-size: 14px; font-weight: 600; color: #065f46;">Observasi</span>
                            </div>
                            <span style="font-size: 24px; font-weight: 700; color: #065f46;">${summary.observasi_count || 0}</span>
                        </div>
                        <p style="margin: 0; font-size: 12px; color: #047857;">Jumlah observasi hari ini</p>
                    </div>
                    
                    <!-- Observasi Area Kritis Hari Ini -->
                    <div style="background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px; padding: 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="material-icons-outlined" style="font-size: 24px; color: #ef4444;">dangerous</i>
                                <span style="font-size: 14px; font-weight: 600; color: #991b1b;">Observasi Area Kritis</span>
                            </div>
                            <span style="font-size: 24px; font-weight: 700; color: #991b1b;">${summary.observasi_area_kritis_count || 0}</span>
                        </div>
                        <p style="margin: 0; font-size: 12px; color: #7f1d1d;">Jumlah observasi area kritis hari ini</p>
                    </div>
                </div>
                
                <div style="padding-top: 16px; border-top: 1px solid #e5e7eb;">
                    <p style="margin: 0; font-size: 12px; color: #6b7280; text-align: center;">
                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">info_outline</i>
                        Data dihitung berdasarkan area yang dipilih untuk hari ini
                    </p>
                </div>
            </div>
        `;
    }
    
    // Render sidebar tab content
    function renderSidebarTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show selected tab content
        const tabContent = document.getElementById(`tabContent${tabName.charAt(0).toUpperCase() + tabName.slice(1)}`);
        if (tabContent) {
            tabContent.classList.add('active');
        }
        
        // Render appropriate list
        switch(tabName) {
            case 'cctv':
                renderCctvList(filteredSidebarData.cctv);
                break;
            case 'sap':
                renderSapList(filteredSidebarData.sap);
                break;
            case 'hazard':
                renderSapList(filteredSidebarData.sap); // Alias untuk kompatibilitas
                break;
            case 'insiden':
                renderInsidenList(filteredSidebarData.insiden);
                break;
            case 'unit':
                renderUnitList(filteredSidebarData.unit);
                break;
            case 'gps':
                renderGpsList(filteredSidebarData.gps);
                break;
            case 'controlroom':
                renderControlRoomList(filteredSidebarData.controlroom);
                break;
            case 'pja':
                if (filteredSidebarData.pja.length === 0) {
                    loadPjaData();
                } else {
                    renderPjaList(filteredSidebarData.pja);
                }
                break;
            case 'evaluasi':
                // Evaluasi content will be rendered by loadEvaluationSummary
                break;
        }
    }
    
    // Highlight and zoom to location on map
    function highlightAndZoomToLocation(location, type, data) {
        if (!location || !map) return;
        
        let coordinates;
        if (Array.isArray(location)) {
            coordinates = location.length === 2 ? ol.proj.fromLonLat(location) : location;
        } else if (location.lat && location.lng) {
            coordinates = ol.proj.fromLonLat([location.lng, location.lat]);
        } else {
            return;
        }
        
        // Zoom to location
        map.getView().animate({
            center: coordinates,
            zoom: 18,
            duration: 1000
        });
        
        // Highlight item in sidebar
        document.querySelectorAll('.sidebar-list-item').forEach(item => {
            item.classList.remove('active');
        });
        
        const activeItem = document.querySelector(`.sidebar-list-item[data-type="${type}"][data-id="${data.id || data.user_id || data.no_kecelakaan || data.integration_id || data.unit_id}"]`);
        if (activeItem) {
            activeItem.classList.add('active');
            activeItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    // Filter sidebar data
    // Data CCTV diambil langsung dari database (cctvLocations), bukan dari WMS atau GeoJSON
    function filterSidebarData(searchTerm) {
        const term = searchTerm.toLowerCase().trim();
        
        if (!term) {
            // Reset ke data asli dari database
            filteredSidebarData.cctv = [...(cctvLocations || [])];
            filteredSidebarData.sap = [...(sapData || [])];
            filteredSidebarData.insiden = [...(insidenDataset || [])];
            filteredSidebarData.unit = [...(unitVehicles || [])];
            // GPS data akan di-reset oleh loadUserGpsData() jika perlu
            // Jangan reset GPS data jika sudah ada, biarkan dari loadUserGpsData()
            // Reset Control Room data ke original jika sudah ada
            if (originalControlRoomData && originalControlRoomData.length > 0) {
                filteredSidebarData.controlroom = JSON.parse(JSON.stringify(originalControlRoomData));
            }
            // Reset PJA data ke original jika sudah ada
            if (originalPjaData && originalPjaData.length > 0) {
                filteredSidebarData.pja = [...originalPjaData];
            }
        } else {
            // Filter data CCTV dari database berdasarkan search term
            filteredSidebarData.cctv = (cctvLocations || []).filter(cctv => {
                const name = (cctv.name || cctv.nama_cctv || cctv.no_cctv || '').toLowerCase();
                const noCctv = (cctv.no_cctv || cctv.nomor_cctv || '').toLowerCase();
                const id = String(cctv.id || '').toLowerCase();
                const site = (cctv.site || '').toLowerCase();
                const perusahaan = (cctv.perusahaan || '').toLowerCase();
                return name.includes(term) || noCctv.includes(term) || id.includes(term) || 
                       site.includes(term) || perusahaan.includes(term);
            });
            
            filteredSidebarData.sap = (sapData || []).filter(sap => {
                const jenisLaporan = (sap.jenis_laporan || '').toLowerCase();
                const aktivitas = (sap.aktivitas_pekerjaan || '').toLowerCase();
                const lokasi = (sap.lokasi || sap.detail_lokasi || '').toLowerCase();
                const taskNumber = String(sap.task_number || '').toLowerCase();
                const pelapor = (sap.pelapor || sap.nama_pelapor || '').toLowerCase();
                return jenisLaporan.includes(term) || aktivitas.includes(term) || 
                       lokasi.includes(term) || taskNumber.includes(term) || pelapor.includes(term);
            });
            
            filteredSidebarData.insiden = (insidenDataset || []).filter(insiden => {
                const noKecelakaan = (insiden.no_kecelakaan || '').toLowerCase();
                const lokasi = (insiden.lokasi || '').toLowerCase();
                return noKecelakaan.includes(term) || lokasi.includes(term);
            });
            
            filteredSidebarData.unit = (unitVehicles || []).filter(unit => {
                const unitId = (unit.unit_id || unit.unit_name || unit.integration_id || '').toLowerCase();
                const id = String(unit.integration_id || unit.unit_id || '').toLowerCase();
                return unitId.includes(term) || id.includes(term);
            });
            
            // Filter Control Room data berdasarkan search term
            if (filteredSidebarData.controlroom && filteredSidebarData.controlroom.length > 0) {
                filteredSidebarData.controlroom = filteredSidebarData.controlroom.filter(controlRoom => {
                    const name = (controlRoom.name || '').toLowerCase();
                    // Filter juga berdasarkan nama CCTV di dalam control room
                    const hasMatchingCctv = controlRoom.cctv_list && controlRoom.cctv_list.some(cctv => {
                        const cctvName = ((cctv.name || cctv.nama_cctv || cctv.no_cctv || '') + ' ' + (cctv.lokasi_pemasangan || '')).toLowerCase();
                        return cctvName.includes(term);
                    });
                    return name.includes(term) || hasMatchingCctv;
                });
            }
            
            // Filter GPS data berdasarkan search term
            if (filteredSidebarData.gps && filteredSidebarData.gps.length > 0) {
                filteredSidebarData.gps = filteredSidebarData.gps.filter(user => {
                    const fullname = (user.fullname || '').toLowerCase();
                    const npk = (user.npk || '').toLowerCase();
                    const department = (user.department_name || user.division_name || '').toLowerCase();
                    const position = ((user.functional_position || '') + ' ' + (user.structural_position || '')).toLowerCase();
                    return fullname.includes(term) || npk.includes(term) || department.includes(term) || position.includes(term);
                });
            }
            
            // Filter PJA data berdasarkan search term
            if (originalPjaData && originalPjaData.length > 0) {
                filteredSidebarData.pja = originalPjaData.filter(pja => {
                    const namaPja = (pja.nama_pja || '').toLowerCase();
                    const site = (pja.site || '').toLowerCase();
                    const lokasi = (pja.lokasi || '').toLowerCase();
                    const detailLokasi = (pja.detail_lokasi || '').toLowerCase();
                    const employeeName = (pja.employee_name || '').toLowerCase();
                    const nik = (pja.nik || '').toLowerCase();
                    const kodeSid = (pja.kode_sid || '').toLowerCase();
                    const pjaType = (pja.pja_type_name || '').toLowerCase();
                    const pjaCategory = (pja.pja_category_name || '').toLowerCase();
                    const kategoriPja = (pja.kategori_pja || '').toLowerCase();
                    return namaPja.includes(term) || site.includes(term) || lokasi.includes(term) || 
                           detailLokasi.includes(term) || employeeName.includes(term) || nik.includes(term) ||
                           kodeSid.includes(term) || pjaType.includes(term) || pjaCategory.includes(term) ||
                           kategoriPja.includes(term);
                });
            }
        }
        
        updateTabCounts();
        renderSidebarTab(currentSidebarTab);
    }
    
    // Sidebar event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize icon toolbar - set CCTV as active by default
        const defaultIconBtn = document.querySelector('.icon-toolbar-btn[data-tab="cctv"]');
        if (defaultIconBtn) {
            defaultIconBtn.classList.add('active');
        }
        
        // Toggle sidebar collapse
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mapSidebar = document.getElementById('mapSidebar');
        
        if (sidebarToggle && mapSidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebarCollapsed = !sidebarCollapsed;
                if (sidebarCollapsed) {
                    mapSidebar.classList.add('collapsed');
                } else {
                    mapSidebar.classList.remove('collapsed');
                }
            });
        }
        
        // Icon Toolbar switching - New implementation
        document.querySelectorAll('.icon-toolbar-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabName = this.dataset.tab;
                
                // Update active icon toolbar button
                document.querySelectorAll('.icon-toolbar-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Open sidebar if collapsed
                const mapSidebar = document.getElementById('mapSidebar');
                if (mapSidebar && mapSidebar.classList.contains('collapsed')) {
                    mapSidebar.classList.remove('collapsed');
                }
                
                // Render tab content
                currentSidebarTab = tabName;
                renderSidebarTab(tabName);
            });
        });
        
        // Legacy tab switching (for backward compatibility)
        document.querySelectorAll('.sidebar-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabName = this.dataset.tab;
                
                // Update active tab
                document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Sync with icon toolbar
                document.querySelectorAll('.icon-toolbar-btn').forEach(b => b.classList.remove('active'));
                const iconBtn = document.querySelector(`.icon-toolbar-btn[data-tab="${tabName}"]`);
                if (iconBtn) {
                    iconBtn.classList.add('active');
                }
                
                // Render tab content
                currentSidebarTab = tabName;
                renderSidebarTab(tabName);
            });
        });
        
        // Search functionality
        const searchInput = document.getElementById('sidebarSearchInput');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    filterSidebarData(this.value);
                }, 300);
            });
        }
        
        // Week filter untuk SAP
        const sapWeekFilter = document.getElementById('sapWeekFilter');
        if (sapWeekFilter) {
            // Set default week (minggu ini - Senin sampai Senin)
            const today = new Date();
            const monday = new Date(today);
            monday.setDate(today.getDate() - (today.getDay() === 0 ? 6 : today.getDay() - 1));
            const year = monday.getFullYear();
            const week = getWeekNumber(monday);
            const weekValue = `${year}-W${String(week).padStart(2, '0')}`;
            sapWeekFilter.value = weekValue;
            updateSapWeekRange();
            
            // JANGAN load SAP data untuk week default saat pertama kali load
            // Gunakan hanya SAP hari ini yang sudah di-filter di awal untuk performa
            // User bisa memilih week lain jika ingin melihat data lebih banyak
            console.log('Skipping initial SAP week load. Using only today\'s SAP data for better performance.');
            
            // Set flag bahwa SAP data sudah ready (dari filter hari ini)
            let sapDataLoading = false;
            
            // Initialize sidebar data langsung dengan SAP hari ini
            setTimeout(() => {
                initializeSidebarData();
            }, 100);
            
            sapWeekFilter.addEventListener('change', function() {
                updateSapWeekRange();
                // Reload SAP data berdasarkan week yang dipilih
                loadSapDataByWeek(this.value);
            });
        } else {
            // Jika tidak ada week filter, initialize sidebar data langsung
            setTimeout(() => {
                initializeSidebarData();
            }, 500);
        }
    });
    
    // Helper function untuk mendapatkan week number
    function getWeekNumber(date) {
        const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        const dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    }
    
    // Update SAP week range display
    function updateSapWeekRange() {
        const sapWeekFilter = document.getElementById('sapWeekFilter');
        const sapWeekRange = document.getElementById('sapWeekRange');
        if (!sapWeekFilter || !sapWeekRange) return;
        
        const weekValue = sapWeekFilter.value;
        if (!weekValue) {
            sapWeekRange.textContent = 'Week: -';
            return;
        }
        
        // Parse week value (format: YYYY-Www)
        const [year, week] = weekValue.split('-W');
        const weekStart = getDateOfISOWeek(parseInt(week), parseInt(year));
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekStart.getDate() + 6);
        
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const startStr = `${weekStart.getDate()} ${months[weekStart.getMonth()]} ${weekStart.getFullYear()}`;
        const endStr = `${weekEnd.getDate()} ${months[weekEnd.getMonth()]} ${weekEnd.getFullYear()}`;
        
        sapWeekRange.textContent = `Week: ${startStr} - ${endStr}`;
    }
    
    // Helper function untuk mendapatkan tanggal Senin dari week number
    function getDateOfISOWeek(week, year) {
        const simple = new Date(year, 0, 1 + (week - 1) * 7);
        const dow = simple.getDay();
        const ISOweekStart = simple;
        if (dow <= 4) {
            ISOweekStart.setDate(simple.getDate() - simple.getDay() + 1);
        } else {
            ISOweekStart.setDate(simple.getDate() + 8 - simple.getDay());
        }
        return ISOweekStart;
    }
    
    // Load SAP data berdasarkan week yang dipilih
    function loadSapDataByWeek(weekValue) {
        return new Promise((resolve, reject) => {
            if (!weekValue) {
                reject('No week value provided');
                return;
            }
            
            const [year, week] = weekValue.split('-W');
            const weekStart = getDateOfISOWeek(parseInt(week), parseInt(year));
            // Set waktu ke 00:00:00 untuk Senin
            weekStart.setHours(0, 0, 0, 0);
            
            // Format: YYYY-MM-DD HH:MM:SS untuk ClickHouse (Senin 00:00:00)
            // Gunakan format lokal untuk menghindari masalah timezone
            const yearStr = weekStart.getFullYear();
            const monthStr = String(weekStart.getMonth() + 1).padStart(2, '0');
            const dayStr = String(weekStart.getDate()).padStart(2, '0');
            const weekStartStr = `${yearStr}-${monthStr}-${dayStr} 00:00:00`;
            
            // Format tanggal untuk display
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6);
            const startStr = `${weekStart.getDate()} ${months[weekStart.getMonth()]} ${weekStart.getFullYear()}`;
            const endStr = `${weekEnd.getDate()} ${months[weekEnd.getMonth()]} ${weekEnd.getFullYear()}`;
            
            console.log('Loading SAP data for week:', weekValue, 'Start:', weekStartStr, 'Date:', weekStart);
            
            // Show SweetAlert loading
            Swal.fire({
                title: 'Memuat Data SAP',
                html: `Memuat data untuk week: <strong>${startStr} - ${endStr}</strong><br><small>Mohon tunggu...</small>`,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Show loading indicator
            const sapTabCount = document.getElementById('sapTabCount');
            if (sapTabCount) {
                sapTabCount.textContent = '...';
            }
            
            // Call API untuk mendapatkan SAP data berdasarkan week
            // Tambahkan timeout 60 detik untuk query yang lebih lama
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 60000); // 60 detik timeout
            
            fetch(`{{ route('maps.api.filtered-data') }}?week_start=${encodeURIComponent(weekStartStr)}&show_sap=true&show_hazard=true`, {
                signal: controller.signal
            })
                .then(response => {
                    clearTimeout(timeoutId);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.data && (data.data.sap || data.data.hazard)) {
                        // Gunakan sap jika ada, jika tidak gunakan hazard (alias)
                        const newSapData = data.data.sap || data.data.hazard || [];
                        
                        // Filter data berdasarkan tanggal_pelaporan untuk memastikan sesuai week
                        const weekEnd = new Date(weekStart);
                        weekEnd.setDate(weekStart.getDate() + 7); // Senin berikutnya
                        weekEnd.setHours(0, 0, 0, 0);
                        
                        const filteredSapData = newSapData.filter(sap => {
                            if (!sap.tanggal_pelaporan && !sap.detected_at) return false;
                            
                            try {
                                const sapDate = new Date(sap.tanggal_pelaporan || sap.detected_at);
                                sapDate.setHours(0, 0, 0, 0);
                                return sapDate >= weekStart && sapDate < weekEnd;
                            } catch (e) {
                                console.warn('Invalid date format:', sap.tanggal_pelaporan || sap.detected_at);
                                return false;
                            }
                        });
                        
                        console.log('SAP data loaded:', filteredSapData.length, 'items for week', weekValue, 'out of', newSapData.length, 'total');
                        
                        // Simpan semua data per week untuk count di tab
                        sapDataAllWeek = [...filteredSapData];
                        
                        // Urutkan semua data berdasarkan tanggal terbaru
                        const sortedSapDataAll = [...filteredSapData].sort((a, b) => {
                            const dateA = new Date(a.tanggal_pelaporan || a.detected_at || 0);
                            const dateB = new Date(b.tanggal_pelaporan || b.detected_at || 0);
                            return dateB - dateA; // Terbaru di atas
                        });
                        
                        // Filter data hari ini untuk sidebar
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        const todayStr = today.toISOString().split('T')[0];
                        
                        const sapDataToday = sortedSapDataAll.filter(sap => {
                            if (!sap.tanggal_pelaporan && !sap.detected_at) return false;
                            try {
                                const sapDate = new Date(sap.tanggal_pelaporan || sap.detected_at);
                                sapDate.setHours(0, 0, 0, 0);
                                const sapDateStr = sapDate.toISOString().split('T')[0];
                                return sapDateStr === todayStr;
                            } catch (e) {
                                return false;
                            }
                        });
                        
                        // Map: Hanya tampilkan 1000 data terbaru dari semua data per week
                        const sapDataForMap = sortedSapDataAll.slice(0, 1000);
                        
                        // Update global sapData (untuk map)
                        sapData = sapDataForMap;
                        
                        // Update filtered sidebar data (hanya data hari ini untuk sidebar)
                        filteredSidebarData.sap = sapDataToday;
                        sapDataForSidebar = sapDataToday;
                        
                        // Update tab counts (menggunakan semua data per week untuk count)
                        updateTabCounts();
                        
                        // Update sidebar list jika tab SAP aktif (tampilkan hanya data hari ini)
                        if (currentSidebarTab === 'sap') {
                            renderSapList(filteredSidebarData.sap);
                        }
                        
                        // Update map markers (hanya 1000 terbaru dari semua data per week)
                        updateSapMarkersOnMap(sapDataForMap);
                        
                        // Update evaluation alerts jika toggle evaluasi aktif
                        if (evaluationEnabled) {
                            showEvaluationAlerts();
                        }
                        
                        console.log(`Week total: ${sapDataAllWeek.length} SAP items | Sidebar (today): ${sapDataToday.length} SAP items | Map: ${sapDataForMap.length} SAP markers (limited to 1000)`);
                        
                        // Close SweetAlert loading
                        Swal.close();
                        
                        resolve(filteredSapData);
                    } else {
                        console.warn('No SAP data returned for week', weekValue, data);
                        // Set empty jika tidak ada data
                        sapDataAllWeek = [];
                        sapData = [];
                        sapDataForSidebar = [];
                        filteredSidebarData.sap = [];
                        updateTabCounts();
                        if (currentSidebarTab === 'sap') {
                            renderSapList([]);
                        }
                        updateSapMarkersOnMap([]);
                        
                        // Update evaluation alerts jika toggle evaluasi aktif
                        if (evaluationEnabled) {
                            showEvaluationAlerts();
                        }
                        
                        // Close SweetAlert loading
                        Swal.close();
                        
                        resolve([]);
                    }
                })
                .catch(error => {
                    clearTimeout(timeoutId);
                    
                    // Close loading SweetAlert
                    Swal.close();
                    
                    // Show error message
                    let errorMessage = 'Terjadi kesalahan saat memuat data SAP.';
                    if (error.name === 'AbortError') {
                        errorMessage = 'Request timeout. Data terlalu besar atau koneksi lambat. Silakan coba lagi.';
                        console.error('SAP data request timeout after 60 seconds');
                    } else {
                        console.error('Error loading SAP data:', error);
                        if (error.message) {
                            errorMessage = error.message;
                        }
                    }
                    
                    // Set empty pada error
                    sapDataAllWeek = [];
                    sapData = [];
                    sapDataForSidebar = [];
                    filteredSidebarData.sap = [];
                    updateTabCounts();
                    if (currentSidebarTab === 'sap') {
                        renderSapList([]);
                    }
                    
                    // Show error message to user
                    const sapTabCount = document.getElementById('sapTabCount');
                    if (sapTabCount) {
                        sapTabCount.textContent = '0';
                    }
                    
                    // Show error SweetAlert
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Memuat Data',
                        text: errorMessage,
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    });
                    
                    reject(error);
                });
        });
    }
    
    // Update SAP markers di map
    function updateSapMarkersOnMap(sapDataArray) {
        if (!hazardLayer) return;
        
        // Clear existing SAP markers (hanya yang dari SAP, bukan yang lain)
        const source = hazardLayer.getSource();
        const featuresToRemove = [];
        source.forEachFeature(function(feature) {
            // Hapus hanya feature SAP (yang punya task_number atau jenis_laporan)
            if (feature.get('task_number') || feature.get('jenis_laporan')) {
                featuresToRemove.push(feature);
            }
        });
        featuresToRemove.forEach(feature => source.removeFeature(feature));
        
        // OPTIMIZED: Batch rendering untuk SAP markers
        // Data yang diterima sudah dibatasi 1000 terbaru dari loadSapDataByWeek
        const MAX_SAP_MARKERS = 1000;
        const BATCH_SIZE = 100;
        
        if (sapDataArray && sapDataArray.length > 0) {
            // Pastikan data diurutkan berdasarkan tanggal terbaru (jika belum)
            const sortedData = [...sapDataArray].sort((a, b) => {
                const dateA = new Date(a.tanggal_pelaporan || a.detected_at || 0);
                const dateB = new Date(b.tanggal_pelaporan || b.detected_at || 0);
                return dateB - dateA; // Terbaru di atas
            });
            
            // Ambil maksimal 1000 data terbaru untuk map
            const limitedData = sortedData.slice(0, MAX_SAP_MARKERS);
            
            const source = hazardLayer.getSource();
            const features = [];
            let markerCount = 0;
            
            limitedData.forEach(function(sap) {
                if (markerCount >= MAX_SAP_MARKERS) {
                    return;
                }
                
                if (sap.location && sap.location.lat && sap.location.lng) {
                    const feature = new ol.Feature({
                        geometry: new ol.geom.Point(
                            ol.proj.fromLonLat([sap.location.lng, sap.location.lat])
                        ),
                        id: sap.id,
                        task_number: sap.task_number,
                        jenis_laporan: sap.jenis_laporan,
                        aktivitas_pekerjaan: sap.aktivitas_pekerjaan,
                        lokasi: sap.lokasi,
                        description: sap.description,
                        data: sap
                    });
                    features.push(feature);
                    markerCount++;
                }
            });
            
            // Batch add features
            if (features.length > 0) {
                let index = 0;
                function addBatch() {
                    const batch = features.slice(index, index + BATCH_SIZE);
                    if (batch.length > 0) {
                        source.addFeatures(batch);
                        index += BATCH_SIZE;
                        if (index < features.length) {
                            requestAnimationFrame(addBatch);
                        } else {
                            console.log('SAP markers updated on map:', features.length, 'markers displayed (out of', sapDataArray.length, 'total)');
                        }
                    }
                }
                requestAnimationFrame(addBatch);
            }
        } else {
            console.log('SAP markers updated on map: 0 markers');
        }
    }

    // ============================================
    // Employee Location Monitoring & Telegram Notification
    // ============================================
    
    // Cache untuk menyimpan lokasi user sebelumnya
    let employeeLocationCache = {};
    let monitoringInterval = null;
    let isMonitoringActive = false;
    
    /**
     * Function to find location from user coordinates
     * Mencari lokasi area kerja berdasarkan koordinat latitude/longitude
     */
    function findLocationFromCoordinates(latitude, longitude) {
        if (!latitude || !longitude || !window.areaKerjaLayers) {
            return null;
        }
        
        // Convert to number (handle comma as decimal separator)
        const lat = parseFloat(String(latitude).replace(',', '.'));
        const lng = parseFloat(String(longitude).replace(',', '.'));
        
        if (isNaN(lat) || isNaN(lng)) {
            return null;
        }
        
        // Iterate through all area kerja layers
        for (const layer of window.areaKerjaLayers) {
            if (!layer.getVisible()) continue;
            
            const source = layer.getSource();
            if (!source) continue;
            
            const features = source.getFeatures();
            
            for (const feature of features) {
                const geometry = feature.getGeometry();
                if (!geometry) continue;
                
                // Check if coordinate is inside this geometry
                if (isCoordinateInGeometry(lat, lng, geometry)) {
                    const props = feature.getProperties();
                    return {
                        id_lokasi: props.id_lokasi || null,
                        lokasi: props.lokasi || '',
                        site: props.site || '',
                        perusahaan: props.perusahaan || '',
                        area_kerja: props.area_kerja || '',
                        luasan: props.luasan || 0,
                        geometry: geometry // Keep geometry for further checks
                    };
                }
            }
        }
        
        return null; // No location found
    }
    
    /**
     * Function to check if should send periodic notification
     * Mengirim notifikasi periodik setiap 5 menit jika user masih di area yang sama
     */
    function shouldSendPeriodicNotification(employeeId) {
        const cached = employeeLocationCache[employeeId];
        if (!cached) return false;
        
        const now = new Date();
        const lastNotification = new Date(cached.lastNotificationTime || 0);
        const minutesDiff = (now - lastNotification) / (1000 * 60);
        
        // Send notification every 5 minutes if user is still in the same area
        return minutesDiff >= 5;
    }
    
    /**
     * Function to send Telegram notification
     * Mengirim notifikasi Telegram dengan informasi lokasi, SAP, CCTV, dan PJ
     */
    async function sendTelegramNotification(employee, location) {
        try {
            console.log(`[Telegram] Preparing notification for ${employee.nama || employee.kode_sid} at ${location.lokasi}`);
            
            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
            
            // Get location details (SAP count, CCTV count, PJ)
            const summaryResponse = await fetch('{{ route("maps.api.evaluation-summary") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    type: 'area_kerja',
                    id_lokasi: location.id_lokasi,
                    lokasi_name: location.lokasi
                })
            });
            
            const summaryData = await summaryResponse.json();
            const summary = summaryData.data || summaryData;
            
            // Calculate total SAP
            const inspeksiCount = summary.inspeksi_count || 0;
            const hazardCount = summary.hazard_count || 0;
            const inspeksiHazardCount = inspeksiCount + hazardCount; // Gabungkan Inspeksi dan Hazard
            const inspeksiHazardOpenCount = (summary.inspeksi_open_count || 0) + (summary.hazard_open_count || 0);
            const coachingCount = summary.coaching_count || 0;
            const coachingOpenCount = summary.coaching_open_count || 0;
            const observasiCount = summary.observasi_count || 0;
            const observasiOpenCount = summary.observasi_open_count || 0;
            const observasiAreaKritisCount = summary.observasi_area_kritis_count || 0;
            const observasiAreaKritisOpenCount = summary.observasi_area_kritis_open_count || 0;
            const totalSap = inspeksiHazardCount + coachingCount + observasiCount;
            
            // Get detail lists
            const inspeksiHazardList = summary.inspeksi_hazard_list || [];
            const coachingList = summary.coaching_list || [];
            const observasiList = summary.observasi_list || [];
            const observasiAreaKritisList = summary.observasi_area_kritis_list || [];
            
            // Format message dengan HTML untuk tampilan card-like yang lebih menarik
            const lokasiName = location.lokasi || 'Area Kerja';
            const perusahaan = location.perusahaan || employee.nama_perusahaan || 'N/A';
            const site = location.site || 'N/A';
            const areaKerja = location.area_kerja || location.lokasi || 'N/A'; // Use lokasi if area_kerja is empty
            const cctvCount = summary.cctv_list ? summary.cctv_list.length : 0;
            const waktu = new Date().toLocaleString('id-ID', { 
                timeZone: 'Asia/Makassar',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const deviceInfo = employee.device_info ? employee.device_info.split('\n')[0].substring(0, 50) + '...' : 'N/A';
            
            // Function to create progress bar visualization
            const createProgressBar = (value, max, length = 10) => {
                if (max === 0) return '▱'.repeat(length);
                const filled = Math.round((value / max) * length);
                const empty = length - filled;
                return '▰'.repeat(filled) + '▱'.repeat(empty);
            };
            
            // Calculate max value for progress bars (gunakan inspeksiHazardCount yang sudah digabung)
            const maxSap = Math.max(inspeksiHazardCount, coachingCount, observasiCount, 1);
            
            // Format dengan HTML untuk tampilan card-like yang lebih menarik
            const message = `🔷 <b>USER DI AREA KERJA</b> 🔷

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
👤 <b>INFORMASI USER</b>
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
<b>Nama:</b> ${employee.nama || employee.kode_sid || 'Unknown'}
<b>Kode SID:</b> <code>${employee.kode_sid || 'N/A'}</code>
<b>Perusahaan:</b> ${perusahaan}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📍 <b>INFORMASI LOKASI</b>
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
<b>Lokasi:</b> ${lokasiName}
<b>Site:</b> ${site}
<b>Area Kerja:</b> ${areaKerja}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 <b>STATISTIK AREA</b>
━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📈 <b>TOTAL SAP:</b> <b>${totalSap.toLocaleString('id-ID')}</b>

<b>📋 Detail SAP:</b>

🔍⚠️ <b>Inspeksi & Hazard:</b> <b>${inspeksiHazardCount.toLocaleString('id-ID')}</b> | <b>Open:</b> ${inspeksiHazardOpenCount.toLocaleString('id-ID')}
   ${createProgressBar(inspeksiHazardCount, maxSap)} ${inspeksiHazardCount > 0 ? Math.round((inspeksiHazardCount / maxSap) * 100) : 0}%
${inspeksiHazardList.length > 0 ? '\n   <b>📝 Top 5:</b>\n' + inspeksiHazardList.slice(0, 5).map((item, idx) => {
   const nomor = (item.nomor_laporan || 'N/A').toString();
   const status = (item.status || 'N/A').toString();
   return `   ${idx + 1}. <code>${nomor.length > 20 ? nomor.substring(0, 20) + '...' : nomor}</code> [${status}]`;
}).join('\n') : '   <i>Tidak ada data</i>'}

💬 <b>Coaching:</b> <b>${coachingCount.toLocaleString('id-ID')}</b> | <b>Open:</b> ${coachingOpenCount.toLocaleString('id-ID')}
   ${createProgressBar(coachingCount, maxSap)} ${coachingCount > 0 ? Math.round((coachingCount / maxSap) * 100) : 0}%
${coachingList.length > 0 ? '\n   <b>📝 Top 5:</b>\n' + coachingList.slice(0, 5).map((item, idx) => {
   const nomor = (item.nomor_laporan || 'N/A').toString();
   const status = (item.status || 'N/A').toString();
   return `   ${idx + 1}. <code>${nomor.length > 20 ? nomor.substring(0, 20) + '...' : nomor}</code> [${status}]`;
}).join('\n') : '   <i>Tidak ada data</i>'}

👁️ <b>Observasi:</b> <b>${observasiCount.toLocaleString('id-ID')}</b> | <b>Open:</b> ${observasiOpenCount.toLocaleString('id-ID')}
   ${createProgressBar(observasiCount, maxSap)} ${observasiCount > 0 ? Math.round((observasiCount / maxSap) * 100) : 0}%
${observasiList.length > 0 ? '\n   <b>📝 Top 5:</b>\n' + observasiList.slice(0, 5).map((item, idx) => {
   const nomor = (item.nomor_laporan || 'N/A').toString();
   const status = (item.status || 'N/A').toString();
   return `   ${idx + 1}. <code>${nomor.length > 20 ? nomor.substring(0, 20) + '...' : nomor}</code> [${status}]`;
}).join('\n') : '   <i>Tidak ada data</i>'}

⚠️ <b>Observasi Area Kritis:</b> <b>${observasiAreaKritisCount.toLocaleString('id-ID')}</b> | <b>Open:</b> ${observasiAreaKritisOpenCount.toLocaleString('id-ID')}
${observasiAreaKritisList.length > 0 ? '\n   <b>📝 Top 5:</b>\n' + observasiAreaKritisList.slice(0, 5).map((item, idx) => {
   const nomor = (item.nomor_laporan || 'N/A').toString();
   const status = (item.status || 'N/A').toString();
   return `   ${idx + 1}. <code>${nomor.length > 20 ? nomor.substring(0, 20) + '...' : nomor}</code> [${status}]`;
}).join('\n') : '   <i>Tidak ada data</i>'}

─────────────────────────────

📹 <b>CCTV Coverage:</b> <b>${cctvCount.toLocaleString('id-ID')}</b> unit <i>(di lokasi ini)</i>

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🕐 <b>Waktu:</b> ${waktu}
📱 <b>Device:</b> <code>${deviceInfo}</code>`;
            
            // Send to Telegram (csrfToken already declared above)
            const telegramResponse = await fetch('{{ route("maps.api.send-telegram") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    chat_id: '{{ config("services.telegram.chat_id") }}', // Use default chat_id from config
                    message: message,
                    parse_mode: 'HTML' // Use HTML for better formatting
                })
            });
            
            // Check if response is OK
            if (!telegramResponse.ok) {
                const errorText = await telegramResponse.text();
                console.error(`[Telegram] ✗ HTTP Error ${telegramResponse.status}:`, errorText);
                return false;
            }
            
            const telegramResult = await telegramResponse.json();
            
            if (telegramResult.success) {
                console.log(`[Telegram] ✓ Notification sent successfully for ${employee.nama || employee.kode_sid}`);
                return true;
            } else {
                console.error(`[Telegram] ✗ Failed to send notification:`, telegramResult.error || telegramResult.message);
                return false;
            }
            
        } catch (error) {
            console.error('[Telegram] Error sending notification:', error);
            // If error is JSON parse error, log the response
            if (error.message && error.message.includes('JSON')) {
                console.error('[Telegram] Response was not valid JSON. Check server error logs.');
            }
            return false;
        }
    }
    
    /**
     * Function to monitor employees and send Telegram notifications
     * Memonitor lokasi employee dan mengirim notifikasi ketika masuk area kerja
     */
    async function monitorEmployeesAndNotify() {
        if (!isMonitoringActive) {
            return;
        }
        
        try {
            // Get employee location data
            const response = await fetch('{{ route("maps.api.employee-location") }}?limit=1000');
            const data = await response.json();
            
            if (!data.success || !data.employees || data.employees.length === 0) {
                console.log('[Monitor] No employee location data available');
                return;
            }
            
            console.log(`[Monitor] Checking ${data.employees.length} employees...`);
            
            // Check if area kerja layers are loaded
            if (!window.areaKerjaLayers || window.areaKerjaLayers.length === 0) {
                console.log('[Monitor] Area kerja layers not loaded yet, waiting...');
                return;
            }
            
            const employees = data.employees;
            let notificationCount = 0;
            
            for (const employee of employees) {
                const employeeId = employee.employee_id || employee.id;
                const lat = employee.latitude;
                const lng = employee.longitude;
                
                if (!employeeId || !lat || !lng) continue;
                
                // Find location from coordinates
                const location = findLocationFromCoordinates(lat, lng);
                
                if (!location) {
                    // Employee is outside any work area
                    if (employeeLocationCache[employeeId]) {
                        // Employee left an area, clear cache
                        console.log(`[Monitor] ${employee.nama || employee.kode_sid} left work area`);
                        delete employeeLocationCache[employeeId];
                    }
                    continue;
                }
                
                // Check if employee is in a new location or same location
                const previousLocation = employeeLocationCache[employeeId];
                const isNewLocation = !previousLocation || 
                                     (previousLocation.id_lokasi !== location.id_lokasi);
                
                // Update cache
                const now = new Date();
                employeeLocationCache[employeeId] = {
                    ...location,
                    employee: employee,
                    timestamp: now,
                    lastNotificationTime: previousLocation?.lastNotificationTime || null
                };
                
                // Send notification if new location or periodic check
                if (isNewLocation) {
                    console.log(`[Monitor] ${employee.nama || employee.kode_sid} entered new area: ${location.lokasi}`);
                    const sent = await sendTelegramNotification(employee, location);
                    if (sent) {
                        employeeLocationCache[employeeId].lastNotificationTime = now;
                        notificationCount++;
                    }
                } else if (shouldSendPeriodicNotification(employeeId)) {
                    console.log(`[Monitor] ${employee.nama || employee.kode_sid} periodic notification for: ${location.lokasi}`);
                    const sent = await sendTelegramNotification(employee, location);
                    if (sent) {
                        employeeLocationCache[employeeId].lastNotificationTime = now;
                        notificationCount++;
                    }
                }
            }
            
            if (notificationCount > 0) {
                console.log(`[Monitor] ✓ Sent ${notificationCount} notifications`);
            }
            
        } catch (error) {
            console.error('[Monitor] Error monitoring employees:', error);
        }
    }
    
    /**
     * Start monitoring employees
     * Memulai monitoring lokasi employee dengan interval tertentu
     */
    function startEmployeeMonitoring(intervalSeconds = 30) {
        if (monitoringInterval) {
            console.log('[Monitor] Monitoring already active');
            return;
        }
        
        isMonitoringActive = true;
        console.log(`[Monitor] Starting employee monitoring (interval: ${intervalSeconds}s)`);
        
        // Run immediately
        monitorEmployeesAndNotify();
        
        // Then run at interval
        monitoringInterval = setInterval(() => {
            monitorEmployeesAndNotify();
        }, intervalSeconds * 1000);
    }
    
    /**
     * Stop monitoring employees
     */
    function stopEmployeeMonitoring() {
        if (monitoringInterval) {
            clearInterval(monitoringInterval);
            monitoringInterval = null;
            isMonitoringActive = false;
            console.log('[Monitor] Employee monitoring stopped');
        }
    }
    
    // Auto-start monitoring after area layers are loaded
    // Wait for area layers to be loaded before starting monitoring
    let monitoringStartAttempts = 0;
    const maxMonitoringStartAttempts = 30; // Try for 30 seconds
    
    function tryStartMonitoring() {
        if (window.areaKerjaLayers && window.areaKerjaLayers.length > 0) {
            // Layers are loaded, start monitoring
            startEmployeeMonitoring(30); // Check every 30 seconds
        } else {
            monitoringStartAttempts++;
            if (monitoringStartAttempts < maxMonitoringStartAttempts) {
                // Retry after 1 second
                setTimeout(tryStartMonitoring, 1000);
            } else {
                console.warn('[Monitor] Area kerja layers not loaded after 30 seconds, monitoring not started');
            }
        }
    }
    
    // Start trying to start monitoring after page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(tryStartMonitoring, 5000); // Wait 5 seconds after DOM ready
        });
    } else {
        setTimeout(tryStartMonitoring, 5000); // Wait 5 seconds
    }
    
    // Charts menggunakan script dari template index.js
    // Script chart akan di-load dari build/js/index.js
</script>
<script src="{{ URL::asset('build/plugins/apexchart/apexcharts.min.js') }}"></script>
<script src="{{ URL::asset('build/js/index.js') }}"></script>
<script src="{{ URL::asset('build/plugins/peity/jquery.peity.min.js') }}"></script>
<script>
    // Initialize peity charts untuk Monthly dan Yearly
    $(document).ready(function() {
        // Donut charts will be initialized by updateStatisticsBySite
        // Just ensure peity is loaded first
        
        // Update chart2 dengan data yang benar setelah template di-load
        
        // Tunggu sebentar untuk memastikan chart sudah di-render oleh index.js
        setTimeout(function() {
            if (typeof ApexCharts !== 'undefined') {
                // Update chart2 dengan data yang benar dan height yang sesuai
                var chart2Element = document.querySelector("#chart2");
                if (chart2Element && chart2Element._apexcharts) {
                    var chart2 = chart2Element._apexcharts;
                    // Gunakan nilai yang sama dengan yang ditampilkan di teks
                    var initialCoverageValue = parseFloat({{ $initialCriticalCoveragePercentage }});
                    // Update series dengan data yang benar (pastikan format sama dengan teks)
                    chart2.updateSeries([initialCoverageValue]);
                    // Update height untuk card kecil
                    chart2.updateOptions({
                        chart: {
                            height: 138
                        }
                    }, false, false);
                }
                
                // Update chart4 dengan data hazard detection
                var chart4Element = document.querySelector("#chart4");
                if (chart4Element && chart4Element._apexcharts) {
                    var chart4 = chart4Element._apexcharts;
                    // Data untuk Active Hazards dan Resolved Hazards
                    chart4.updateSeries([{
                        name: 'Active Hazards',
                        data: [30, 40, 35, 50, 49, 60, 70, 91, 125]
                    }, {
                        name: 'Resolved Hazards',
                        data: [20, 30, 25, 40, 39, 50, 60, 71, 95]
                    }]);
                }
            }
        }, 500);
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- TourGuide JS Script -->
<script src="https://unpkg.com/@sjmc11/tourguidejs/dist/tour.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
{{-- <script>
    // Initialize TourGuide
    let tourGuide = null;
    
    document.addEventListener('DOMContentLoaded', function() {
        // Wait for all elements and TourGuide script to be ready
        setTimeout(function() {
            try {
                // Check for TourGuideClient (CDN version uses tourguide namespace)
                let TourGuideClass = null;
                
                if (typeof TourGuideClient !== 'undefined') {
                    TourGuideClass = TourGuideClient;
                } else if (typeof tourguide !== 'undefined' && tourguide.TourGuideClient) {
                    TourGuideClass = tourguide.TourGuideClient;
                } else if (window.tourguide && window.tourguide.TourGuideClient) {
                    TourGuideClass = window.tourguide.TourGuideClient;
                }
                
                if (TourGuideClass) {
                    tourGuide = new TourGuideClass({
                        backdropColor: 'rgba(0, 0, 0, 0.75)',
                        targetPadding: 20,
                        dialogAnimate: true,
                        backdropAnimate: true,
                        showStepDots: true,
                        stepDotsPlacement: 'footer',
                        showStepProgress: true,
                        showButtons: true,
                        closeButton: true,
                        nextLabel: 'Selanjutnya',
                        prevLabel: 'Sebelumnya',
                        finishLabel: 'Selesai',
                        keyboardControls: true,
                        exitOnEscape: true,
                        exitOnClickOutside: false,
                        rememberStep: false,
                        completeOnFinish: true,
                        debug: false,
                        autoScroll: true,
                        autoScrollSmooth: true,
                        autoScrollOffset: 20,
                        dialogWidth: 0,
                        dialogMaxWidth: 380,
                        dialogClass: 'custom-tour-dialog'
                    });
                    
                    // Add button to start tour
                    const startTourBtn = document.createElement('button');
                    startTourBtn.className = 'btn btn-primary position-fixed';
                    startTourBtn.style.cssText = 'bottom: 20px; left: 20px; z-index: 10000; box-shadow: 0 4px 6px rgba(0,0,0,0.3); border-radius: 8px; padding: 10px 20px; font-weight: 500;';
                    startTourBtn.innerHTML = '<i class="material-icons-outlined me-2" style="font-size: 18px; vertical-align: middle;">help_outline</i>Mulai Tour Guide';
                    startTourBtn.onclick = function() {
                        if (tourGuide) {
                            tourGuide.start();
                            
                            // Apply custom styles after tour starts
                            setTimeout(function() {
                                const dialog = document.querySelector('[data-tg-dialog], .tg-dialog, [class*="tg-dialog"]');
                                if (dialog) {
                                    dialog.style.maxWidth = '380px';
                                    dialog.style.width = '100%';
                                    dialog.style.borderRadius = '12px';
                                    dialog.style.overflow = 'hidden';
                                }
                                
                                const footer = document.querySelector('[data-tg-dialog-footer], .tg-dialog-footer, [class*="tg-dialog-footer"]');
                                if (footer) {
                                    footer.style.padding = '12px 20px';
                                    footer.style.borderTop = '1px solid #e5e7eb';
                                    footer.style.backgroundColor = '#f9fafb';
                                    footer.style.display = 'flex';
                                    footer.style.flexDirection = 'column';
                                    footer.style.gap = '10px';
                                }
                                
                                const buttons = document.querySelectorAll('[data-tg-button], .tg-button, [class*="tg-button"]');
                                buttons.forEach(function(btn) {
                                    if (!btn.classList.contains('tg-close-button') && !btn.classList.contains('tg-step-dot')) {
                                        btn.style.flex = '1';
                                        btn.style.padding = '10px 16px';
                                        btn.style.borderRadius = '8px';
                                        btn.style.fontWeight = '500';
                                        btn.style.fontSize = '14px';
                                    }
                                });
                                
                                const stepDots = document.querySelector('[data-tg-step-dots], .tg-step-dots, [class*="tg-step-dots"]');
                                if (stepDots) {
                                    stepDots.style.display = 'flex';
                                    stepDots.style.justifyContent = 'center';
                                    stepDots.style.alignItems = 'center';
                                    stepDots.style.gap = '8px';
                                    stepDots.style.marginBottom = '8px';
                                    stepDots.style.padding = '8px 0';
                                    stepDots.style.flexWrap = 'wrap';
                                }
                            }, 100);
                        }
                    };
                    document.body.appendChild(startTourBtn);
                    
                    // Function to apply custom styles to dialog
                    function applyDialogStyles() {
                        setTimeout(function() {
                            const dialog = document.querySelector('[data-tg-dialog], .tg-dialog, [class*="tg-dialog"]');
                            if (dialog) {
                                dialog.style.maxWidth = '380px';
                                dialog.style.width = '100%';
                                dialog.style.borderRadius = '12px';
                                dialog.style.overflow = 'hidden';
                            }
                            
                            const footer = document.querySelector('[data-tg-dialog-footer], .tg-dialog-footer, [class*="tg-dialog-footer"]');
                            if (footer) {
                                footer.style.padding = '12px 20px';
                                footer.style.borderTop = '1px solid #e5e7eb';
                                footer.style.backgroundColor = '#f9fafb';
                                footer.style.display = 'flex';
                                footer.style.flexDirection = 'column';
                                footer.style.gap = '10px';
                            }
                            
                            const buttons = document.querySelectorAll('[data-tg-button], .tg-button, [class*="tg-button"]');
                            buttons.forEach(function(btn) {
                                if (!btn.classList.contains('tg-close-button') && !btn.classList.contains('tg-step-dot')) {
                                    btn.style.flex = '1';
                                    btn.style.padding = '10px 16px';
                                    btn.style.borderRadius = '8px';
                                    btn.style.fontWeight = '500';
                                    btn.style.fontSize = '14px';
                                }
                            });
                            
                            const stepDots = document.querySelector('[data-tg-step-dots], .tg-step-dots, [class*="tg-step-dots"]');
                            if (stepDots) {
                                stepDots.style.display = 'flex';
                                stepDots.style.justifyContent = 'center';
                                stepDots.style.alignItems = 'center';
                                stepDots.style.gap = '8px';
                                stepDots.style.marginBottom = '8px';
                                stepDots.style.padding = '8px 0';
                                stepDots.style.flexWrap = 'wrap';
                            }
                        }, 50);
                    }
                    
                    // Listen for tour events to apply styles
                    if (tourGuide && typeof tourGuide.on === 'function') {
                        tourGuide.on('step:after', applyDialogStyles);
                        tourGuide.on('start', applyDialogStyles);
                    }
                    
                    console.log('TourGuide initialized successfully');
                } else {
                    console.error('TourGuideClient is not defined. Make sure the script is loaded correctly.');
                }
            } catch (error) {
                console.error('Error initializing TourGuide:', error);
            }
        }, 1500);
    });
</script> --}}
@endsection



