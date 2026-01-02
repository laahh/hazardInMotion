@extends('layouts.master-home')

@section('title', 'Score Card 2025')

@section('css')
<style>
    body {
        background-color: #f8f9fa !important;
        font-family: 'Inter', sans-serif;
    }

    .main-content {
        padding-top: 2rem;
        padding-bottom: 2rem;
        background-color: #f8f9fa;
        min-height: 100vh;
    }

    .dashboard-wrapper {
        max-width: 1400px;
        margin: 0 auto;
    }

    .dashboard-container {
        background: #ffffff;
        padding: 24px 32px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #f3f4f6;
        margin-bottom: 24px;
    }

    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 20px;
        border-bottom: 1px solid #f3f4f6;
    }

    .dashboard-title h1 {
        margin: 0;
        font-size: 24px;
        font-weight: 600;
        color: #1a1a1a;
        letter-spacing: -0.3px;
    }

    .dashboard-title p {
        margin: 4px 0 0;
        color: #6b7280;
        font-size: 14px;
        font-weight: 400;
    }

    .legend {
        display: flex;
        gap: 18px;
        font-size: 12px;
        align-items: center;
    }

    .legend > strong {
        margin-right: 4px;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #6b7280;
    }

    .legend-box {
        width: 20px;
        height: 15px;
        border-radius: 4px;
    }

    .year-badge {
        font-weight: 600;
        font-size: 14px;
        color: #111827;
        padding: 8px 16px;
        background: #f9fafb;
        border-radius: 6px;
    }

    .table-container {
        overflow-x: auto;
    }

    .dashboard-table {
        font-size: 12px;
        margin-top: 0;
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    .dashboard-table thead th {
        background-color: #f9fafb;
        font-weight: 600;
        text-align: center;
        vertical-align: middle;
        padding: 12px 8px;
        border-bottom: 1px solid #e5e7eb;
        color: #6b7280;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .dashboard-table tbody td {
        text-align: center;
        vertical-align: middle;
        padding: 10px 8px;
        border-bottom: 1px solid #f3f4f6;
    }

    .dashboard-table tbody tr:last-child td {
        border-bottom: none;
    }

    .dashboard-table tbody tr {
        transition: background-color 0.2s ease;
    }

    .dashboard-table tbody tr:hover {
        background-color: #f9fafb;
    }

    .param-name {
        text-align: center !important;
        font-weight: 600;
        background-color: #f9fafb;
        color: #111827;
    }

    .contractor-cell {
        text-align: center !important;
        font-size: 12px;
        color: #111827;
    }

    .trend-icon {
        font-size: 14px;
        font-weight: bold;
    }

    .trend-up { color: #28a745; }
    .trend-down { color: #dc3545; }

    /* Legend color (L1-L4) */
    .level-l1 { background-color: #d32f2f; color: #ffffff !important; }
    .level-l2 { background-color: #ff9933; color: #0000 !important; }
    .level-l3 { background-color: #5cb85c; color: #000000 !important; }
    .level-l4 { background-color: #28a745; color: #ffffff !important; }

    /* Color coding detail berdasarkan persentase (mendekati desain awal) */
    .perf-100 { background-color: #00b050 !important; color: #ffffff !important; font-weight: 500; }
    .perf-99  { background-color: #92d050 !important; color: #000000 !important; font-weight: 500; }
    .perf-98  { background-color: #92d050 !important; color: #00000 !important; }
    .perf-97  { background-color: #92d050 !important; color: #000000 !important; }
    .perf-96  { background-color: #00b0f0 !important; color: #ffffff !important; }
    .perf-95  { background-color: #92d050 !important; color: #000000 !important; }
    .perf-94  { background-color: #00b0f0 !important; color: #000000 !important; }
    .perf-93  { background-color: #00b0f0 !important; color: #000000 !important; }
    .perf-92  { background-color: #00b0f0 !important; color: #000000 !important; }
    .perf-91  { background-color: #ffc000 !important; color: #000000 !important; }
    .perf-88  { background-color: #ffc000 !important; color: #000000 !important; }
    .perf-87  { background-color: #ffc000 !important; color: #000000 !important; }
    .perf-84  { background-color: #ffc000 !important; color: #000000 !important; }
    .perf-81  { background-color: #ffc000 !important; color: #000000 !important; }
    .perf-80  { background-color: #ffc000 !important; color: #000000 !important; }
    .perf-78  { background-color: #ff9900 !important; color: #000000 !important; }
    .perf-76  { background-color: #ff9900 !important; color: #000000 !important; }
    .perf-70  { background-color: #ff6600 !important; color: #000000 !important; }
    .perf-64  { background-color: #ff0000 !important; color: #ffffff !important; }
    .perf-62  { background-color: #ff0000 !important; color: #ffffff !important; }
    .perf-55  { background-color: #ff0000 !important; color: #ffffff !important; }
    .perf-50  { background-color: #ff0000 !important; color: #ffffff !important; }
    .perf-45  { background-color: #c00000 !important; color: #ffffff !important; font-weight: 600; }

    .dec-new {
        background-color: #2196F3 !important;
        color: #ffffff !important;
        font-weight: 600;
        
    }

    .avg-cell {
        background-color: #f9fafb;
        font-weight: 600;
        font-size: 13px;
        color: #111827;
    }

    .site-cell {
        background-color: #f9fafb;
        font-weight: 500;
        color: #111827;
    }

    .row-separator td {
        padding: 0;
        border-color: transparent;
        height: 8px;
        background-color: #f8f9fa;
    }

    /* Animasi untuk angka di tabel */
    .value-cell {
        opacity: 0;
        transform: scale(0.8);
        animation: fadeInScale 0.5s ease-out forwards;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
    }

    @keyframes fadeInScale {
        from {
            opacity: 0;
            transform: scale(0.8) translateY(-10px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    /* Hover effect untuk angka */
    .value-cell:hover {
        transform: scale(1.15) !important;
        z-index: 10;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3) !important;
        border-radius: 4px;
    }

    /* Pulse animation untuk highlight */
    @keyframes pulse {
        0%, 100% {
            box-shadow: 0 0 0 0 rgba(0, 0, 0, 0.2);
        }
        50% {
            box-shadow: 0 0 0 8px rgba(0, 0, 0, 0);
        }
    }

    .value-cell.pulse {
        animation: fadeInScale 0.5s ease-out forwards, pulse 2s ease-in-out infinite;
    }

    /* Smooth transition untuk semua value cells */
    .dashboard-table tbody td[class*="perf-"],
    .dashboard-table tbody td.dec-new {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Filter section styling */
    .filter-section {
        background: #ffffff;
        padding: 20px 24px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #f3f4f6;
        margin-bottom: 24px;
        display: flex;
        gap: 20px;
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .filter-group {
        flex: 1;
        min-width: 200px;
        display: flex;
        flex-direction: column;
    }

    .filter-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 12px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-group select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 14px;
        background: #ffffff;
        cursor: pointer;
        transition: all 0.2s ease;
        height: 40px;
        box-sizing: border-box;
        color: #111827;
    }

    .filter-group select:hover {
        border-color: #2196F3;
    }

    .filter-group select:focus {
        outline: none;
        border-color: #2196F3;
        box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
    }

    .btn-reset-filter {
        padding: 8px 20px;
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-reset-filter:hover {
        background: #5a6268;
        transform: translateY(-1px);
    }

    .filter-info {
        font-size: 12px;
        color: #9ca3af;
        margin-top: 6px;
        min-height: 18px;
        line-height: 18px;
        font-weight: 400;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .overview-section {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .overview-section {
            grid-template-columns: 1fr;
        }

        .dashboard-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .filter-section {
            flex-direction: column;
        }

        .filter-group {
            width: 100%;
        }
    }

    /* Overview Section */
    .overview-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }

    .overview-section.framework {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    .overview-card {
        background: #ffffff;
        padding: 24px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #f3f4f6;
        transition: box-shadow 0.2s ease;
    }

    .overview-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .overview-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 16px;
    }

    .overview-card-title {
        color: #6b7280;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0;
    }

    .overview-card-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .overview-card-icon .material-icons-outlined {
        font-size: 28px;
        line-height: 1;
    }

    .overview-card:hover .overview-card-icon {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .icon-blue { background-color: #e3f2fd; color: #1976d2; }
    .icon-green { background-color: #e8f5e9; color: #388e3c; }
    .icon-orange { background-color: #fff3e0; color: #f57c00; }
    .icon-purple { background-color: #f3e5f5; color: #7b1fa2; }
    .icon-red { background-color: #ffebee; color: #c62828; }

    .overview-card-value {
        font-size: 36px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 8px;
        display: flex;
        align-items: baseline;
        gap: 4px;
        line-height: 1;
    }

    .overview-card-unit {
        font-size: 16px;
        font-weight: 500;
        color: #9ca3af;
    }

    .overview-card-subtitle {
        font-size: 12px;
        color: #9ca3af;
        font-weight: 400;
    }

    /* Leaderboard Section */
    .leaderboard-section {
        background: #ffffff;
        padding: 24px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #f3f4f6;
        margin-bottom: 24px;
    }

    .leaderboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f3f4f6;
    }

    .leaderboard-title {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }


    .leaderboard-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 14px;
    }

    .leaderboard-table thead th {
        text-align: left;
        padding: 12px;
        color: #6b7280;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e5e7eb;
        background-color: #f9fafb;
    }

    .leaderboard-table tbody td {
        padding: 16px 12px;
        color: #111827;
        border-bottom: 1px solid #f3f4f6;
    }

    .leaderboard-table tbody tr:last-child td {
        border-bottom: none;
    }

    .leaderboard-table tbody tr {
        transition: background-color 0.2s ease;
    }

    .leaderboard-table tbody tr:hover {
        background-color: #f9fafb;
    }

    .rank-badge {
        display: inline-block;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        text-align: center;
        line-height: 36px;
        font-weight: 700;
        font-size: 14px;
        color: white;
    }

    .rank-1 { background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); }
    .rank-2 { background: linear-gradient(135deg, #C0C0C0 0%, #808080 100%); }
    .rank-3 { background: linear-gradient(135deg, #CD7F32 0%, #8B4513 100%); }
    .rank-other { background: #6c757d; }

    .contractor-name {
        font-weight: 600;
        color: #111827;
    }

    .score-value {
        font-size: 20px;
        font-weight: 700;
        color: #111827;
    }

    .trend-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }

    .trend-badge.up {
        background-color: #ecfdf5;
        color: #059669;
    }

    .trend-badge.down {
        background-color: #fef2f2;
        color: #dc2626;
    }

    .section-title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 20px;
        color: #111827;
        padding-bottom: 12px;
        border-bottom: 1px solid #f3f4f6;
    }

    /* Performance Score Indicator */
    .performance-score {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 32px;
        color: white;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }

    .performance-score::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: pulse 3s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 0.5; }
        50% { transform: scale(1.1); opacity: 0.8; }
    }

    .performance-score-content {
        position: relative;
        z-index: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .score-main {
        display: flex;
        flex-direction: column;
    }

    .score-label {
        font-size: 14px;
        opacity: 0.9;
        margin-bottom: 8px;
        font-weight: 500;
    }

    .score-value-large {
        font-size: 64px;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 8px;
    }

    .score-description {
        font-size: 14px;
        opacity: 0.8;
    }

    .score-trend {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
    }

    .trend-indicator {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        backdrop-filter: blur(10px);
    }

    /* Charts Section */
    .charts-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }

    .chart-card {
        background: #ffffff;
        border-radius: 8px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #f3f4f6;
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f3f4f6;
    }

    .chart-title {
        font-size: 16px;
        font-weight: 600;
        color: #111827;
        margin: 0;
    }

    .chart-container {
        position: relative;
        height: 300px;
    }

    .form-select {
        padding: 6px 32px 6px 12px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 13px;
        background: white;
        cursor: pointer;
        color: #374151;
        transition: all 0.2s ease;
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 8px center;
        background-repeat: no-repeat;
        background-size: 16px;
    }

    .form-select:hover {
        border-color: #d1d5db;
    }

    .form-select:focus {
        outline: none;
        border-color: #2196F3;
        box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
    }

    /* Leaderboard Enhanced */
    .leaderboard-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        border-radius: 8px;
        transition: all 0.2s ease;
        margin-bottom: 12px;
        background: #ffffff;
        border: 1px solid #f3f4f6;
    }

    .leaderboard-item:hover {
        background: #f9fafb;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transform: translateX(4px);
    }

    .leaderboard-rank {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 18px;
        color: white;
        flex-shrink: 0;
    }

    .leaderboard-info {
        flex: 1;
        min-width: 0;
    }

    .leaderboard-name {
        font-weight: 600;
        color: #111827;
        font-size: 15px;
        margin-bottom: 4px;
    }

    .leaderboard-meta {
        font-size: 12px;
        color: #6b7280;
    }

    .leaderboard-score {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
        min-width: 120px;
    }

    .score-number {
        font-size: 24px;
        font-weight: 700;
        color: #111827;
    }

    .progress-bar-container {
        width: 200px;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 1s ease-out;
        background: linear-gradient(90deg, #10b981 0%, #059669 100%);
    }

    /* Mini Chart in Cards */
    .mini-chart {
        height: 60px;
        margin-top: 12px;
    }

    /* Performance Distribution */
    .distribution-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .distribution-card {
        background: #ffffff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #f3f4f6;
        text-align: center;
    }

    .distribution-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 12px;
        font-size: 24px;
    }

    .distribution-icon .material-icons-outlined {
        font-size: 24px;
        line-height: 1;
    }

    .distribution-value {
        font-size: 32px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 4px;
    }

    .distribution-label {
        font-size: 12px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .performance-score-content {
            flex-direction: column;
            align-items: flex-start;
            gap: 20px;
        }

        .score-value-large {
            font-size: 48px;
        }

        .leaderboard-item {
            flex-direction: column;
            align-items: flex-start;
        }

        .leaderboard-score {
            width: 100%;
            align-items: flex-start;
        }

        .progress-bar-container {
            width: 100%;
        }
    }
</style>
@endsection

@section('content')
<div class="container-fluid dashboard-wrapper">
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="dashboard-title">
                <h1>Score Card 2025</h1>
                <p>OHS Scorecard Overview & Performance Dashboard</p>
            </div>
            <div class="d-flex align-items-center gap-3">
            <div class="legend">
                <strong>Legend</strong>
                <div class="legend-item">
                    <div class="legend-box" style="background-color: #d32f2f;"></div>
                    <span>L1</span>
                </div>
                <div class="legend-item">
                    <div class="legend-box" style="background-color: #ff9933;"></div>
                    <span>L2</span>
                </div>
                <div class="legend-item">
                    <div class="legend-box" style="background-color: #5cb85c;"></div>
                    <span>L3</span>
                </div>
                <div class="legend-item">
                    <div class="legend-box" style="background-color: #28a745;"></div>
                    <span>L4</span>
                </div>
            </div>
            <div class="year-badge">Year: 2025</div>
            </div>
        </div>

        <!-- Performance Score Indicator -->
        <div class="performance-score" id="performanceScore">
            <!-- Will be populated by JavaScript -->
        </div>

        <!-- Overview Section -->
        <div class="section-title">📊 Performance Overview</div>
        <div class="overview-section" id="overviewSection">
            <!-- Overview cards will be populated by JavaScript -->
        </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Framework Performance Trend</h3>
                    <select id="periodFilter" class="form-select" style="width: auto; min-width: 120px;">
                        <option value="monthly">Monthly</option>
                        <option value="weekly">Weekly</option>
                        <option value="daily">Daily</option>
                    </select>
                </div>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Score Distribution</h3>
                </div>
                <div class="chart-container">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Framework Distribution -->
        <div class="section-title">📋 Framework Distribution</div>
        <div class="distribution-grid" id="frameworkSection">
            <!-- Framework cards will be populated by JavaScript -->
        </div>

        <!-- Leaderboard Section -->
        <div class="leaderboard-section">
            <div class="leaderboard-header">
                <h3 class="leaderboard-title">
                    <i class="material-icons-outlined" style="font-size: 24px; vertical-align: middle;">emoji_events</i>
                    <span style="vertical-align: middle; margin-left: 8px;">Top Performers - Contractor Leaderboard</span>
                </h3>
            </div>
            <div id="leaderboardBody">
                <!-- Leaderboard items will be populated by JavaScript -->
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-group">
                <label for="filterParameter">Filter Nama Parameter</label>
                <select id="filterParameter">
                    <option value="">Semua Parameter</option>
                </select>
                <div class="filter-info" id="paramCount"></div>
            </div>
            <div class="filter-group">
                <label for="filterSite">Filter Site</label>
                <select id="filterSite">
                    <option value="">Semua Site</option>
                </select>
                <div class="filter-info" id="siteCount"></div>
            </div>
        </div>

        <div class="table-container">
            <div style="background: #ffffff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #f3f4f6; overflow: hidden;">
                <table class="dashboard-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 160px;">Nama<br>Parameter</th>
                        <th style="width: 70px;">Average</th>
                        <th style="width: 70px;">Site</th>
                        <th style="width: 90px;">Kontraktor</th>
                        <th style="width: 55px;">Trend</th>
                            <th colspan="12" style="background-color: #f9fafb;">Historical</th>
                    </tr>
                    <tr>
                        <th colspan="5"></th>
                        <th>Jan</th>
                        <th>Feb</th>
                        <th>Mar</th>
                        <th>Apr</th>
                        <th>May</th>
                        <th>Jun</th>
                        <th>Jul</th>
                        <th>Aug</th>
                        <th>Sep</th>
                        <th>Oct</th>
                        <th>Nov</th>
                        <th style="background-color: #2196F3; color: #ffffff;">Dec<br>(New)</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const data = [
            {
                name: "Safety Behavior Treatment",
                avg: "75%",
                site: "BMO 1",
                contractors: [
                    { name: "PT BUMA", trend: "down", values: [100, 94, 84, 70, 80, 98, 98, 98, 95, 99, 97, 45] },
                    { name: "PT FAD", trend: "up", values: [87, 80, 96, 99, 55, 96, 96, 95, 99, 96, 98, 96] },
                    { name: "PT KDC", trend: "up", values: [76, 98, 97, 93, 97, 99, 97, 99, 96, 97, 95, 92] },
                    { name: "PT MTL", trend: "down", values: [62, 99, 96, 98, 95, 88, 81, 96, 64, 98, 99, 78] }
                ]
            },
        {
                name: "Traffic Management",
                avg: "88%",
                site: "BMO 1",
                contractors: [
                    { name: "PT BUMA", trend: "down", values: [100, 94, 84, 70, 80, 98, 98, 98, 95, 99, 97, 45] },
                    { name: "PT FAD", trend: "up", values: [87, 80, 96, 99, 55, 96, 96, 95, 99, 96, 98, 96] },
                    { name: "PT KDC", trend: "up", values: [76, 98, 97, 93, 97, 99, 97, 99, 96, 97, 95, 92] },
                    { name: "PT MTL", trend: "down", values: [62, 99, 96, 98, 95, 88, 81, 96, 64, 98, 99, 78] }
                ]
            },
        {
                name: "SPIP Management",
                avg: "98%",
                site: "BMO 1",
                contractors: [
                    { name: "PT BUMA", trend: "down", values: [100, 94, 84, 70, 80, 98, 98, 98, 95, 99, 97, 45] },
                    { name: "PT FAD", trend: "up", values: [87, 80, 96, 99, 55, 96, 96, 95, 99, 96, 98, 96] },
                    { name: "PT KDC", trend: "up", values: [76, 98, 97, 93, 97, 99, 97, 99, 96, 97, 95, 92] },
                    { name: "PT MTL", trend: "down", values: [62, 99, 96, 98, 95, 88, 81, 96, 64, 98, 99, 78] }
                ]
            },
        {
                name: "Risk Management",
                avg: "91%",
                site: "BMO 1",
                contractors: [
                    { name: "PT BUMA", trend: "down", values: [100, 94, 84, 70, 80, 98, 98, 98, 95, 99, 97, 45] },
                    { name: "PT FAD", trend: "up", values: [87, 80, 96, 99, 55, 96, 96, 95, 99, 96, 98, 96] },
                    { name: "PT KDC", trend: "up", values: [76, 98, 97, 93, 97, 99, 97, 99, 96, 97, 95, 92] },
                    { name: "PT MTL", trend: "down", values: [62, 99, 96, 98, 95, 88, 81, 96, 64, 98, 99, 78] }
                ]
            },
        {
                name: "Emergency Response",
                avg: "90%",
                site: "BMO 1",
                contractors: [
                    { name: "PT BUMA", trend: "down", values: [100, 94, 84, 70, 80, 98, 98, 98, 95, 99, 97, 45] },
                    { name: "PT FAD", trend: "up", values: [87, 80, 96, 99, 55, 96, 96, 95, 99, 96, 98, 96] },
                    { name: "PT KDC", trend: "up", values: [76, 98, 97, 93, 97, 99, 97, 99, 96, 97, 95, 92] },
                    { name: "PT MTL", trend: "down", values: [62, 99, 96, 98, 95, 88, 81, 96, 64, 98, 99, 78] }
                ]
            },
        {
                name: "Investigation",
                avg: "88%",
                site: "BMO 1",
                contractors: [
                    { name: "PT BUMA", trend: "down", values: [100, 94, 84, 70, 80, 98, 98, 98, 95, 99, 97, 45] },
                    { name: "PT FAD", trend: "up", values: [87, 80, 96, 99, 55, 96, 96, 95, 99, 96, 98, 96] },
                    { name: "PT KDC", trend: "up", values: [76, 98, 97, 93, 97, 99, 97, 99, 96, 97, 95, 92] },
                    { name: "PT MTL", trend: "down", values: [62, 99, 96, 98, 95, 88, 81, 96, 64, 98, 99, 78] }
                ]
            },
        {
                name: "PJA Performance",
                avg: "87%",
                site: "BMO 1",
                contractors: [
                    { name: "PT BUMA", trend: "down", values: [100, 94, 84, 70, 80, 98, 98, 98, 95, 99, 97, 45] },
                    { name: "PT FAD", trend: "up", values: [87, 80, 96, 99, 55, 96, 96, 95, 99, 96, 98, 96] },
                    { name: "PT KDC", trend: "up", values: [76, 98, 97, 93, 97, 99, 97, 99, 96, 97, 95, 92] },
                    { name: "PT MTL", trend: "down", values: [62, 99, 96, 98, 95, 88, 81, 96, 64, 98, 99, 78] }
                ]
            },
        {
                name: "Fatigue Management",
                avg: "79%",
                site: "BMO 1",
                contractors: [
                    { name: "PT BUMA", trend: "down", values: [100, 94, 84, 70, 80, 98, 98, 98, 95, 99, 97, 45] },
                    { name: "PT FAD", trend: "up", values: [87, 80, 96, 99, 55, 96, 96, 95, 99, 96, 98, 96] },
                    { name: "PT KDC", trend: "up", values: [76, 98, 97, 93, 97, 99, 97, 99, 96, 97, 95, 92] },
                    { name: "PT MTL", trend: "down", values: [62, 99, 96, 98, 95, 88, 81, 96, 64, 98, 99, 78] }
                ]
            },
        {
                name: "Pemenuhan Komitmen Tools",
                avg: "99%",
                site: "BMO 1",
                contractors: [
                    { name: "PT BUMA", trend: "down", values: [100, 94, 84, 70, 80, 98, 98, 98, 95, 99, 97, 45] },
                    { name: "PT FAD", trend: "up", values: [87, 80, 96, 99, 55, 96, 96, 95, 99, 96, 98, 96] },
                    { name: "PT KDC", trend: "up", values: [76, 98, 97, 93, 97, 99, 97, 99, 96, 97, 95, 92] },
                    { name: "PT MTL", trend: "down", values: [62, 99, 96, 98, 95, 88, 81, 96, 64, 98, 99, 78] }
                ]
            },
        {
                name: "Competency",
                avg: "95%",
                site: "BMO 1",
                contractors: [
                    { name: "PT BUMA", trend: "down", values: [100, 94, 84, 70, 80, 98, 98, 98, 95, 99, 97, 45] },
                    { name: "PT FAD", trend: "up", values: [87, 80, 96, 99, 55, 96, 96, 95, 99, 96, 98, 96] },
                    { name: "PT KDC", trend: "up", values: [76, 98, 97, 93, 97, 99, 97, 99, 96, 97, 95, 92] },
                    { name: "PT MTL", trend: "down", values: [62, 99, 96, 98, 95, 88, 81, 96, 64, 98, 99, 78] }
                ]
            }
    ];

    function getColorClass(value) {
        // Gunakan mapping detail per nilai agar warna tiap kotak mengikuti desain tabel asli
        // (100, 99, 98, 97, 96, dst. sudah didefinisikan di CSS .perf-XX)
        const v = Number(value);
        return 'perf-' + v;
    }

    // Function untuk populate filter dropdown
    function populateFilters() {
        const paramSelect = document.getElementById('filterParameter');
        const siteSelect = document.getElementById('filterSite');
        
        // Get unique parameters
        const uniqueParams = [...new Set(data.map(item => item.name))].sort();
        uniqueParams.forEach(param => {
            const option = document.createElement('option');
            option.value = param;
            option.textContent = param;
            paramSelect.appendChild(option);
        });
        
        // Get unique sites
        const uniqueSites = [...new Set(data.map(item => item.site))].sort();
        uniqueSites.forEach(site => {
            const option = document.createElement('option');
            option.value = site;
            option.textContent = site;
            siteSelect.appendChild(option);
        });
        
        updateFilterCounts();
    }

    // Function untuk update filter counts
    function updateFilterCounts() {
        const paramFilter = document.getElementById('filterParameter').value;
        const siteFilter = document.getElementById('filterSite').value;
        
        let filteredData = data;
        if (paramFilter) {
            filteredData = filteredData.filter(item => item.name === paramFilter);
        }
        if (siteFilter) {
            filteredData = filteredData.filter(item => item.site === siteFilter);
        }
        
        const totalParams = filteredData.length;
        const totalRows = filteredData.reduce((sum, item) => sum + item.contractors.length, 0);
        
        document.getElementById('paramCount').textContent = 
            totalParams > 0 ? `${totalParams} parameter, ${totalRows} baris` : 'Tidak ada data';
        document.getElementById('siteCount').textContent = 
            siteFilter ? `Site: ${siteFilter}` : '';
    }

    // Function untuk filter data
    function getFilteredData() {
        const paramFilter = document.getElementById('filterParameter').value;
        const siteFilter = document.getElementById('filterSite').value;
        
        let filtered = data;
        if (paramFilter) {
            filtered = filtered.filter(item => item.name === paramFilter);
        }
        if (siteFilter) {
            filtered = filtered.filter(item => item.site === siteFilter);
        }
        
        return filtered;
    }

    // Function untuk reset filter
    function resetFilters() {
        document.getElementById('filterParameter').value = '';
        document.getElementById('filterSite').value = '';
        updateFilterCounts();
        renderTable();
        setTimeout(applyInteractiveEffects, 100);
    }

    function renderTable() {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = ''; // Clear existing rows
        
        const filteredData = getFilteredData();

        filteredData.forEach((param, idx) => {
            param.contractors.forEach((contractor, cIdx) => {
                const row = document.createElement('tr');

                if (cIdx === 0) {
                    const nameCell = document.createElement('td');
                    nameCell.className = 'param-name';
                    nameCell.rowSpan = param.contractors.length;
                    
                    // Add link styling and onclick
                    nameCell.style.cursor = 'pointer';
                    nameCell.style.color = '#2196F3';
                    nameCell.style.textDecoration = 'none';
                    nameCell.title = 'Click to view details';
                    nameCell.style.transition = 'all 0.2s ease';
                    
                    nameCell.addEventListener('mouseenter', function() {
                        this.style.color = '#1976d2';
                        this.style.textDecoration = 'underline';
                    });
                    
                    nameCell.addEventListener('mouseleave', function() {
                        this.style.color = '#2196F3';
                        this.style.textDecoration = 'none';
                    });
                    
                    nameCell.onclick = function() {
                        const url = "{{ route('score-card.show', ':param') }}".replace(':param', encodeURIComponent(param.name));
                        window.location.href = url;
                    };
                    
                    nameCell.textContent = param.name;
                    row.appendChild(nameCell);

                    const avgCell = document.createElement('td');
                    avgCell.className = 'avg-cell';
                    avgCell.rowSpan = param.contractors.length;
                    avgCell.textContent = param.avg;
                    row.appendChild(avgCell);

                    const siteCell = document.createElement('td');
                    siteCell.className = 'site-cell';
                    siteCell.rowSpan = param.contractors.length;
                    siteCell.textContent = param.site;
                    row.appendChild(siteCell);
                }

                const contrCell = document.createElement('td');
                contrCell.className = 'contractor-cell';
                contrCell.textContent = contractor.name;
                row.appendChild(contrCell);

                const trendCell = document.createElement('td');
                trendCell.innerHTML = contractor.trend === 'up'
                    ? '<span class="trend-icon trend-up">↑</span>'
                    : '<span class="trend-icon trend-down">↓</span>';
                row.appendChild(trendCell);

                contractor.values.forEach((value, vIdx) => {
                    const valueCell = document.createElement('td');
                    const baseClass = vIdx === 11 ? 'dec-new' : getColorClass(value);
                    valueCell.className = baseClass + ' value-cell';
                    valueCell.textContent = value + '%';
                    
                    // Delay animasi bertahap untuk efek cascade
                    const delay = (idx * param.contractors.length + cIdx) * 50 + (vIdx * 30);
                    valueCell.style.animationDelay = (delay / 1000) + 's';
                    
                    row.appendChild(valueCell);
                });

                tbody.appendChild(row);
            });

            if (idx < filteredData.length - 1) {
                const separatorRow = document.createElement('tr');
                separatorRow.className = 'row-separator';
                const separatorCell = document.createElement('td');
                separatorCell.colSpan = 17;
                separatorRow.appendChild(separatorCell);
                tbody.appendChild(separatorRow);
            }
        });
    }

    // Mapping framework berdasarkan nama parameter
    const frameworkMapping = {
        // Leadership
        'PJA Performance': 'Leadership',
        'Supervisory Layering System': 'Leadership',
        'SPIP Management': 'Leadership',
        'SPIP management': 'Leadership',
        'K3L Compliance': 'Leadership',
        'CSMS': 'Leadership',
        
        // People
        'Safety Behavior Treatment': 'People',
        'Competency': 'People',
        'Speak up': 'People',
        'Health Management': 'People',
        'Emergency Response': 'People',
        
        // Process
        'Traffic Management': 'Process',
        'Geotechnical Management': 'Process',
        'Fatigue Management': 'Process',
        'Risk Management': 'Process',
        
        // Teknologi
        'Pemenuhan Komitmen Tools': 'Teknologi',
        'Pemenuhan komitmen tools pengawasan langsung berjarak': 'Teknologi',
        'Pengawasan Control Room': 'Teknologi',
        
      
    };

    // Function untuk menghitung overview statistics
    function calculateOverview() {
        const filteredData = getFilteredData();
        const totalParams = filteredData.length;
        const allContractors = [...new Set(filteredData.flatMap(item => item.contractors.map(c => c.name)))];
        const totalContractors = allContractors.length;
        
        // Calculate overall average score
        let totalScore = 0;
        let totalValues = 0;
        filteredData.forEach(param => {
            param.contractors.forEach(contractor => {
                contractor.values.forEach(value => {
                    totalScore += Number(value);
                    totalValues++;
                });
            });
        });
        const overallAvg = totalValues > 0 ? Math.round((totalScore / totalValues) * 100) / 100 : 0;
        
        // Count total sites
        const totalSites = [...new Set(filteredData.map(item => item.site))].length;
        
        // Count by framework - hanya 4 framework utama
        const frameworkCounts = {};
        const allowedFrameworks = ['Leadership', 'People', 'Process', 'Teknologi'];
        filteredData.forEach(param => {
            const framework = frameworkMapping[param.name];
            // Hanya hitung jika framework ada di mapping dan termasuk 4 framework utama
            if (framework && allowedFrameworks.includes(framework)) {
                frameworkCounts[framework] = (frameworkCounts[framework] || 0) + 1;
            }
        });
        
        return {
            totalParams,
            totalContractors,
            overallAvg,
            totalSites,
            frameworkCounts
        };
    }

    // Function untuk render performance score
    function renderPerformanceScore() {
        const stats = calculateOverview();
        const performanceScore = document.getElementById('performanceScore');
        
        // Calculate trend (compare with previous period - simplified)
        const trendValue = 2.5; // This would come from actual data comparison
        const trendDirection = trendValue >= 0 ? 'up' : 'down';
        
        performanceScore.innerHTML = `
            <div class="performance-score-content">
                <div class="score-main">
                    <div class="score-label">Overall Performance Score</div>
                    <div class="score-value-large">${stats.overallAvg}%</div>
                    <div class="score-description">Based on ${stats.totalParams} parameters across ${stats.totalContractors} contractors</div>
                </div>
                <div class="score-trend">
                    <div class="trend-indicator">
                        <i class="material-icons-outlined">${trendDirection === 'up' ? 'trending_up' : 'trending_down'}</i>
                        <span>${Math.abs(trendValue)}% vs last period</span>
                    </div>
                    <div style="font-size: 12px; opacity: 0.8;">Performance Trend</div>
                </div>
            </div>
        `;
    }

    function calculateMonthlyAverages() {
        const filteredData = getFilteredData();
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        const monthlyScores = months.map((_, monthIdx) => {
            let total = 0;
            let count = 0;
            filteredData.forEach(param => {
                param.contractors.forEach(contractor => {
                    if (contractor.values[monthIdx] !== undefined) {
                        total += contractor.values[monthIdx];
                        count++;
                    }
                });
            });
            return count > 0 ? Math.round(total / count) : 0;
        });
        
        return {
            scores: monthlyScores,
            params: Array(12).fill(filteredData.length),
            contractors: Array(12).fill([...new Set(filteredData.flatMap(item => item.contractors.map(c => c.name)))].length),
            sites: Array(12).fill([...new Set(filteredData.map(item => item.site))].length)
        };
    }

    function getColorForCard(color, alpha = 1) {
        const colors = {
            blue: `rgba(33, 150, 243, ${alpha})`,
            green: `rgba(56, 142, 60, ${alpha})`,
            orange: `rgba(245, 124, 0, ${alpha})`,
            purple: `rgba(123, 31, 162, ${alpha})`
        };
        return colors[color] || colors.blue;
    }

    // Function untuk render overview cards dengan mini charts
    function renderOverview() {
        const stats = calculateOverview();
        const overviewSection = document.getElementById('overviewSection');
        const frameworkSection = document.getElementById('frameworkSection');
        
        // Get monthly averages for mini charts
        const monthlyData = calculateMonthlyAverages();
        
        const cards = [
            {
                title: 'Total Parameter OHS',
                value: stats.totalParams,
                subtitle: 'Program OHS Scorecard',
                color: 'blue',
                icon: 'assessment',
                chartData: monthlyData.params
            },
            {
                title: 'Total Kontraktor',
                value: stats.totalContractors,
                subtitle: 'Aktif',
                color: 'green',
                icon: 'groups',
                chartData: monthlyData.contractors
            },
            {
                title: 'Overall Average Score',
                value: stats.overallAvg + '%',
                subtitle: 'Rata-rata semua parameter',
                color: 'orange',
                icon: 'trending_up',
                chartData: monthlyData.scores
            },
            {
                title: 'Total Site',
                value: stats.totalSites,
                subtitle: 'Lokasi',
                color: 'purple',
                icon: 'location_on',
                chartData: monthlyData.sites
            }
        ];
        
        overviewSection.innerHTML = cards.map((card, index) => `
            <div class="overview-card">
                <div class="overview-card-header">
                    <div style="flex: 1;">
                        <div class="overview-card-title">${card.title}</div>
                    </div>
                    <div class="overview-card-icon icon-${card.color}">
                        <i class="material-icons-outlined">${card.icon}</i>
                    </div>
                </div>
                <div class="overview-card-value">
                    ${typeof card.value === 'string' && card.value.includes('%') 
                        ? card.value.replace('%', '<span class="overview-card-unit">%</span>')
                        : card.value}
                </div>
                <div class="overview-card-subtitle">${card.subtitle}</div>
                <div class="mini-chart">
                    <canvas id="miniChart${index}"></canvas>
                </div>
            </div>
        `).join('');
        
        // Render mini charts
        setTimeout(() => {
            cards.forEach((card, index) => {
                const canvas = document.getElementById(`miniChart${index}`);
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'],
                            datasets: [{
                                data: card.chartData,
                                borderColor: getColorForCard(card.color),
                                backgroundColor: getColorForCard(card.color, 0.1),
                                borderWidth: 2,
                                tension: 0.4,
                                fill: true,
                                pointRadius: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: { enabled: false }
                            },
                            scales: {
                                x: { display: false },
                                y: { display: false }
                            }
                        }
                    });
                }
            });
        }, 100);

        // Render framework distribution - hanya 4 framework utama
        const allowedFrameworks = ['Leadership', 'People', 'Process', 'Teknologi'];
        const frameworkOrder = ['Leadership', 'People', 'Process', 'Teknologi'];
        
        const frameworkColors = {
            'Leadership': 'blue',
            'People': 'green',
            'Process': 'orange',
            'Teknologi': 'purple'
        };

        // Framework icons mapping
        const frameworkIcons = {
            'Leadership': 'leaderboard',
            'People': 'people',
            'Process': 'settings',
            'Teknologi': 'computer'
        };

        // Filter dan urutkan hanya framework yang diizinkan
        const frameworkCards = frameworkOrder
            .filter(framework => stats.frameworkCounts[framework] !== undefined && stats.frameworkCounts[framework] > 0)
            .map(framework => ({
                title: framework,
                value: stats.frameworkCounts[framework] || 0,
                subtitle: 'Parameter',
                color: frameworkColors[framework] || 'purple'
            }));

        if (frameworkCards.length > 0) {
            frameworkSection.innerHTML = frameworkCards.map(card => {
                const icon = frameworkIcons[card.title] || 'category';
                return `
                <div class="distribution-card">
                    <div class="distribution-icon icon-${card.color}">
                        <i class="material-icons-outlined">${icon}</i>
                    </div>
                    <div class="distribution-value">${card.value}</div>
                    <div class="distribution-label">${card.title}</div>
                </div>
            `;
            }).join('');
        } else {
            frameworkSection.innerHTML = '';
        }
    }

    // Function untuk menghitung leaderboard
    function calculateLeaderboard() {
        const filteredData = getFilteredData();
        const contractorStats = {};
        
        filteredData.forEach(param => {
            param.contractors.forEach(contractor => {
                if (!contractorStats[contractor.name]) {
                    contractorStats[contractor.name] = {
                        name: contractor.name,
                        totalScore: 0,
                        totalValues: 0,
                        paramCount: 0,
                        upTrends: 0,
                        downTrends: 0
                    };
                }
                
                contractor.values.forEach(value => {
                    contractorStats[contractor.name].totalScore += Number(value);
                    contractorStats[contractor.name].totalValues++;
                });
                
                contractorStats[contractor.name].paramCount++;
                
                if (contractor.trend === 'up') {
                    contractorStats[contractor.name].upTrends++;
                } else {
                    contractorStats[contractor.name].downTrends++;
                }
            });
        });
        
        // Calculate average for each contractor
        const leaderboard = Object.values(contractorStats).map(stat => ({
            name: stat.name,
            averageScore: stat.totalValues > 0 ? Math.round((stat.totalScore / stat.totalValues) * 100) / 100 : 0,
            paramCount: stat.paramCount,
            trend: stat.upTrends >= stat.downTrends ? 'up' : 'down'
        }));
        
        // Sort by average score descending
        leaderboard.sort((a, b) => b.averageScore - a.averageScore);
        
        return leaderboard;
    }

    // Function untuk render leaderboard dengan visual yang lebih menarik
    function renderLeaderboard() {
        const leaderboard = calculateLeaderboard();
        const leaderboardBody = document.getElementById('leaderboardBody');
        
        const maxScore = leaderboard.length > 0 ? leaderboard[0].averageScore : 100;
        
        leaderboardBody.innerHTML = leaderboard.map((contractor, index) => {
            const rank = index + 1;
            const rankClass = rank === 1 ? 'rank-1' : rank === 2 ? 'rank-2' : rank === 3 ? 'rank-3' : 'rank-other';
            const progressWidth = (contractor.averageScore / maxScore) * 100;
            const scoreColor = contractor.averageScore >= 90 ? '#10b981' : contractor.averageScore >= 75 ? '#f59e0b' : '#ef4444';
            
            return `
                <div class="leaderboard-item">
                    <div class="leaderboard-rank ${rankClass}">${rank}</div>
                    <div class="leaderboard-info">
                        <div class="leaderboard-name">${contractor.name}</div>
                        <div class="leaderboard-meta">${contractor.paramCount} parameters • 
                            <span class="trend-badge ${contractor.trend}" style="display: inline-flex; margin-left: 4px;">
                                ${contractor.trend === 'up' ? '↑' : '↓'} ${contractor.trend === 'up' ? 'Improving' : 'Declining'}
                            </span>
                        </div>
                    </div>
                    <div class="leaderboard-score">
                        <div class="score-number" style="color: ${scoreColor};">${contractor.averageScore}%</div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: ${progressWidth}%; background: linear-gradient(90deg, ${scoreColor} 0%, ${scoreColor}dd 100%);"></div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        // Animate progress bars
        setTimeout(() => {
            const progressBars = document.querySelectorAll('.progress-bar-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        }, 100);
    }

    // Function untuk menghitung data per framework berdasarkan period
    function calculateFrameworkData(period = 'monthly') {
        const filteredData = getFilteredData();
        const frameworks = ['Leadership', 'People', 'Process', 'Teknologi'];
        const frameworkData = {};
        
        // Initialize framework data
        frameworks.forEach(fw => {
            frameworkData[fw] = [];
        });
        
        // Generate labels berdasarkan period
        let labels = [];
        let dataPoints = 12;
        
        if (period === 'daily') {
            // 30 hari terakhir
            dataPoints = 30;
            const today = new Date();
            for (let i = 29; i >= 0; i--) {
                const date = new Date(today);
                date.setDate(date.getDate() - i);
                labels.push(date.getDate() + '/' + (date.getMonth() + 1));
            }
        } else if (period === 'weekly') {
            // 12 minggu terakhir
            dataPoints = 12;
            const today = new Date();
            for (let i = 11; i >= 0; i--) {
                const date = new Date(today);
                date.setDate(date.getDate() - (i * 7));
                const weekNum = Math.ceil(date.getDate() / 7);
                labels.push('W' + weekNum + ' ' + date.toLocaleDateString('id-ID', { month: 'short' }));
            }
        } else {
            // Monthly (default) - 12 bulan
            labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            dataPoints = 12;
        }
        
        // Framework base values untuk membuat nilai berbeda-beda per framework
        const frameworkBaseValues = {
            'Leadership': { base: 88, trend: [0, 2, 1, -1, 3, 2, 0, -2, 1, 3, 2, -1] },    // ~88% dengan variasi
            'People': { base: 82, trend: [1, -1, 2, 0, 1, -2, 2, 1, 0, -1, 2, 1] },        // ~82% dengan variasi
            'Process': { base: 85, trend: [-1, 1, 0, 2, -1, 1, 2, 0, -1, 2, 1, 0] },       // ~85% dengan variasi
            'Teknologi': { base: 92, trend: [0, 1, -1, 1, 0, -1, 1, 0, 1, -1, 0, 1] }      // ~92% dengan variasi
        };
        
        // Calculate average per framework per period dengan variasi berbeda
        frameworks.forEach(framework => {
            const baseConfig = frameworkBaseValues[framework];
            for (let i = 0; i < dataPoints; i++) {
                let total = 0;
                let count = 0;
                
                filteredData.forEach(param => {
                    const paramFramework = frameworkMapping[param.name];
                    if (paramFramework === framework) {
                        param.contractors.forEach(contractor => {
                            if (period === 'monthly') {
                                // Langsung gunakan data bulanan
                                if (contractor.values[i] !== undefined) {
                                    total += contractor.values[i];
                                    count++;
                                }
                            } else if (period === 'weekly') {
                                // Untuk weekly, ambil rata-rata dari data bulanan dalam periode tersebut
                                const monthIndex = Math.min(Math.floor(i / 4), 11);
                                if (contractor.values[monthIndex] !== undefined) {
                                    total += contractor.values[monthIndex];
                                    count++;
                                }
                            } else {
                                // Daily - interpolate dari data bulanan
                                const monthIndex = Math.min(Math.floor(i / (dataPoints / 12)), 11);
                                if (contractor.values[monthIndex] !== undefined) {
                                    total += contractor.values[monthIndex];
                                    count++;
                                }
                            }
                        });
                    }
                });
                
                // Hitung rata-rata dasar dari data aktual
                let avgValue = count > 0 ? (total / count) : 0;
                
                // Jika ada data, tambahkan variasi berdasarkan framework untuk membuat perbedaan
                if (count > 0) {
                    // Ambil trend factor berdasarkan index (untuk monthly langsung, untuk weekly/daily interpolate)
                    let trendIndex = i;
                    if (period === 'weekly') {
                        trendIndex = Math.min(Math.floor(i / 4), 11);
                    } else if (period === 'daily') {
                        trendIndex = Math.min(Math.floor(i / (dataPoints / 12)), 11);
                    }
                    
                    const trendFactor = baseConfig.trend[trendIndex] || 0;
                    // Adjust nilai dengan menambahkan perbedaan dari base value
                    const adjustment = (baseConfig.base - avgValue) * 0.4 + trendFactor;
                    avgValue = avgValue + adjustment;
                } else {
                    // Jika tidak ada data, gunakan base value dengan trend
                    let trendIndex = i;
                    if (period === 'weekly') {
                        trendIndex = Math.min(Math.floor(i / 4), 11);
                    } else if (period === 'daily') {
                        trendIndex = Math.min(Math.floor(i / (dataPoints / 12)), 11);
                    }
                    const trendFactor = baseConfig.trend[trendIndex] || 0;
                    avgValue = baseConfig.base + trendFactor;
                }
                
                // Ensure value is within reasonable bounds (0-100)
                avgValue = Math.max(0, Math.min(100, avgValue));
                
                frameworkData[framework].push(Math.round(avgValue * 100) / 100);
            }
        });
        
        return { labels, frameworkData };
    }

    // Function untuk render charts
    let trendChartInstance = null;
    function renderCharts() {
        const filteredData = getFilteredData();
        const period = document.getElementById('periodFilter')?.value || 'monthly';
        const { labels, frameworkData } = calculateFrameworkData(period);
        
        // Trend Chart - Multi Axis Line Chart
        const trendCtx = document.getElementById('trendChart');
        if (trendCtx) {
            // Destroy existing chart if any
            if (trendChartInstance) {
                trendChartInstance.destroy();
            }
            
            // Framework colors
            const frameworkColors = {
                'Leadership': { border: '#2196F3', bg: 'rgba(33, 150, 243, 0.1)' },
                'People': { border: '#4CAF50', bg: 'rgba(76, 175, 80, 0.1)' },
                'Process': { border: '#FF9800', bg: 'rgba(255, 152, 0, 0.1)' },
                'Teknologi': { border: '#9C27B0', bg: 'rgba(156, 39, 176, 0.1)' }
            };
            
            // Create datasets for each framework - semua menggunakan y-axis yang sama
            const datasets = [
                {
                    label: 'Leadership',
                    data: frameworkData['Leadership'],
                    borderColor: frameworkColors['Leadership'].border,
                    backgroundColor: frameworkColors['Leadership'].bg,
                    borderWidth: 3,
                    tension: 0.4,
                    fill: false,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: frameworkColors['Leadership'].border,
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    yAxisID: 'y'
                },
                {
                    label: 'People',
                    data: frameworkData['People'],
                    borderColor: frameworkColors['People'].border,
                    backgroundColor: frameworkColors['People'].bg,
                    borderWidth: 3,
                    tension: 0.4,
                    fill: false,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: frameworkColors['People'].border,
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    yAxisID: 'y'
                },
                {
                    label: 'Process',
                    data: frameworkData['Process'],
                    borderColor: frameworkColors['Process'].border,
                    backgroundColor: frameworkColors['Process'].bg,
                    borderWidth: 3,
                    tension: 0.4,
                    fill: false,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: frameworkColors['Process'].border,
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    yAxisID: 'y'
                },
                {
                    label: 'Teknologi',
                    data: frameworkData['Teknologi'],
                    borderColor: frameworkColors['Teknologi'].border,
                    backgroundColor: frameworkColors['Teknologi'].bg,
                    borderWidth: 3,
                    tension: 0.4,
                    fill: false,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: frameworkColors['Teknologi'].border,
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    yAxisID: 'y'
                }
            ];
            
            trendChartInstance = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: { size: 12, weight: '500' },
                                color: '#374151'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: { size: 13, weight: '600' },
                            bodyFont: { size: 12 },
                            cornerRadius: 6,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: false,
                            min: 0,
                            max: 100,
                            ticks: {
                                font: { size: 11, weight: '500' },
                                color: '#6b7280',
                                callback: function(value) { return value + '%'; },
                                stepSize: 20
                            },
                            grid: { 
                                color: '#f3f4f6',
                                drawBorder: true,
                                borderColor: '#e5e7eb',
                                borderWidth: 1
                            },
                            title: {
                                display: true,
                                text: 'Performance Score (%)',
                                color: '#374151',
                                font: { size: 12, weight: '600' },
                                padding: { top: 0, bottom: 10 }
                            }
                        },
                        x: {
                            ticks: {
                                font: { size: 10 },
                                color: '#6b7280',
                                maxRotation: period === 'daily' ? 90 : period === 'weekly' ? 45 : 0,
                                minRotation: 0
                            },
                            grid: { 
                                display: true,
                                color: '#f3f4f6',
                                drawBorder: false
                            }
                        }
                    }
                }
            });
        }
        
        // Distribution Chart
        const distCtx = document.getElementById('distributionChart');
        if (distCtx) {
            // Calculate score distribution
            const distribution = {
                excellent: 0, // 90-100
                good: 0,      // 75-89
                fair: 0,      // 60-74
                poor: 0       // <60
            };
            
            filteredData.forEach(param => {
                param.contractors.forEach(contractor => {
                    contractor.values.forEach(value => {
                        if (value >= 90) distribution.excellent++;
                        else if (value >= 75) distribution.good++;
                        else if (value >= 60) distribution.fair++;
                        else distribution.poor++;
                    });
                });
            });
            
            new Chart(distCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Excellent (90-100%)', 'Good (75-89%)', 'Fair (60-74%)', 'Poor (<60%)'],
                    datasets: [{
                        data: [distribution.excellent, distribution.good, distribution.fair, distribution.poor],
                        backgroundColor: [
                            '#10b981',
                            '#3b82f6',
                            '#f59e0b',
                            '#ef4444'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: { size: 11 },
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 10,
                            cornerRadius: 6,
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // Initialize filters, overview, leaderboard, charts and table
    populateFilters();
    renderPerformanceScore();
    renderOverview();
    renderCharts();
    renderLeaderboard();
    renderTable();

    // Event listeners untuk filter
    document.getElementById('filterParameter').addEventListener('change', function() {
        updateFilterCounts();
        renderPerformanceScore();
        renderOverview();
        renderCharts();
        renderLeaderboard();
        renderTable();
        // Re-apply interactive effects after re-render
        setTimeout(applyInteractiveEffects, 100);
    });

    document.getElementById('filterSite').addEventListener('change', function() {
        updateFilterCounts();
        renderPerformanceScore();
        renderOverview();
        renderCharts();
        renderLeaderboard();
        renderTable();
        // Re-apply interactive effects after re-render
        setTimeout(applyInteractiveEffects, 100);
    });

    // Event listener untuk period filter
    const periodFilter = document.getElementById('periodFilter');
    if (periodFilter) {
        periodFilter.addEventListener('change', function() {
            renderCharts();
        });
    }

    // Function untuk apply interactive effects
    function applyInteractiveEffects() {
        const valueCells = document.querySelectorAll('.value-cell');
        
        valueCells.forEach(cell => {
            // Tambahkan efek click untuk highlight dengan pulse
            cell.addEventListener('click', function() {
                // Remove pulse dari semua cells
                valueCells.forEach(c => c.classList.remove('pulse'));
                // Add pulse ke cell yang diklik
                this.classList.add('pulse');
                
                // Remove pulse setelah 2 detik
                setTimeout(() => {
                    this.classList.remove('pulse');
                }, 2000);
            });
        });
    }

    // Apply interactive effects setelah initial render
    setTimeout(applyInteractiveEffects, 100);
</script>
@endsection