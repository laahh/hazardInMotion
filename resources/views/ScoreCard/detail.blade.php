@extends('layouts.master-home')

@section('title', $title . ' - Dashboard')

@section('css')
<style>
    body {
        background-color: #f8f9fa !important;
        font-family: 'Inter', sans-serif;
    }

    .main-content {
        padding-top: 2rem;
        padding-bottom: 2rem;
        min-height: 100vh;
    }

    .dashboard-header {
        background: #ffffff;
        padding: 24px 32px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 4px solid #2196F3;
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

    .btn-back {
        padding: 10px 20px;
        background-color: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        color: #4b5563;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-back:hover {
        background-color: #2196F3;
        color: #ffffff;
        border-color: #2196F3;
    }

    /* Cards Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: #ffffff;
        border-radius: 8px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: box-shadow 0.2s ease;
        border: 1px solid #f3f4f6;
    }

    .stat-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 16px;
    }

    .stat-label {
        color: #6b7280;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .icon-day { 
        background-color: #e3f2fd; 
        color: #1976d2; 
    }
    .icon-week { 
        background-color: #fff3e0; 
        color: #f57c00; 
    }
    .icon-month { 
        background-color: #e8f5e9; 
        color: #388e3c; 
    }

    .stat-value {
        font-size: 36px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 8px;
        display: flex;
        align-items: baseline;
        gap: 4px;
        line-height: 1;
    }

    .stat-unit {
        font-size: 16px;
        font-weight: 500;
        color: #9ca3af;
    }

    .stat-trend {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }

    .trend-up {
        background-color: #ecfdf5;
        color: #059669;
    }

    .trend-down {
        background-color: #fef2f2;
        color: #dc2626;
    }

    /* Charts Section */
    .charts-container {
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
        font-size: 18px;
        font-weight: 600;
        color: #111827;
    }

    .form-select {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 14px;
        font-weight: 400;
        transition: border-color 0.2s ease;
        background: #ffffff;
    }

    .form-select:hover {
        border-color: #2196F3;
    }

    .form-select:focus {
        border-color: #2196F3;
        outline: none;
        box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
    }

    /* Contractors Table */
    .contractor-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .contractor-table th {
        text-align: left;
        padding: 12px;
        color: #6b7280;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e5e7eb;
    }

    .contractor-table td {
        padding: 16px 12px;
        font-size: 14px;
        color: #111827;
        border-bottom: 1px solid #f3f4f6;
    }

    .contractor-table tr:last-child td {
        border-bottom: none;
    }

    .contractor-row {
        transition: background-color 0.2s ease;
    }

    .contractor-row:hover {
        background-color: #f9fafb;
    }

    .progress-bar {
        height: 6px;
        background-color: #e5e7eb;
        border-radius: 3px;
        width: 100%;
        margin-top: 8px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 1s ease-out;
    }

    .bg-success { 
        background-color: #10b981;
    }
    .bg-warning { 
        background-color: #f59e0b;
    }
    .bg-danger { 
        background-color: #ef4444;
    }

    .badge {
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        display: inline-block;
    }

    .badge.bg-success {
        background-color: #10b981 !important;
        color: #ffffff !important;
    }

    .badge.bg-warning {
        background-color: #f59e0b !important;
        color: #ffffff !important;
    }

    .badge.bg-danger {
        background-color: #ef4444 !important;
        color: #ffffff !important;
    }

    /* Breakdown Section */
    .breakdown-section {
        background: #ffffff;
        border-radius: 8px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #f3f4f6;
        margin-bottom: 24px;
    }

    .breakdown-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f3f4f6;
    }

    .breakdown-title {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
    }

    .tab-nav {
        display: flex;
        gap: 8px;
        border-bottom: 2px solid #f3f4f6;
        margin-bottom: 20px;
    }

    .tab-btn {
        padding: 10px 20px;
        background: transparent;
        border: none;
        border-bottom: 2px solid transparent;
        color: #6b7280;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-bottom: -2px;
    }

    .tab-btn:hover {
        color: #2196F3;
    }

    .tab-btn.active {
        color: #2196F3;
        border-bottom-color: #2196F3;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .breakdown-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .breakdown-table th {
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

    .breakdown-table td {
        padding: 14px 12px;
        font-size: 14px;
        color: #111827;
        border-bottom: 1px solid #f3f4f6;
    }

    .breakdown-table tr:last-child td {
        border-bottom: none;
    }

    .breakdown-row {
        transition: background-color 0.2s ease;
    }

    .breakdown-row:hover {
        background-color: #f9fafb;
    }

    .metric-name {
        font-weight: 600;
        color: #111827;
    }

    .metric-value {
        font-weight: 700;
        color: #111827;
    }

    .metric-change {
        font-size: 12px;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 500;
    }

    .change-positive {
        background-color: #ecfdf5;
        color: #059669;
    }

    .change-negative {
        background-color: #fef2f2;
        color: #dc2626;
    }

    .change-neutral {
        background-color: #f3f4f6;
        color: #6b7280;
    }

    /* Traffic Management Section */
    .traffic-section {
        margin-bottom: 24px;
    }

    .traffic-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }

    .traffic-stat-card {
        background: #ffffff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #f3f4f6;
        text-align: center;
    }

    .traffic-stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #2196F3;
        margin-bottom: 4px;
    }

    .traffic-stat-label {
        font-size: 12px;
        color: #6b7280;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .traffic-charts-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }

    .traffic-table-card {
        background: #ffffff;
        border-radius: 8px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #f3f4f6;
    }

    .traffic-table-wrapper {
        overflow-x: auto;
    }

    .traffic-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13px;
    }

    .traffic-table th {
        text-align: left;
        padding: 10px 8px;
        color: #6b7280;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e5e7eb;
        background-color: #f9fafb;
        white-space: nowrap;
    }

    .traffic-table td {
        padding: 12px 8px;
        color: #111827;
        border-bottom: 1px solid #f3f4f6;
    }

    .traffic-table tr:hover {
        background-color: #f9fafb;
    }

    .filter-group {
        display: flex;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .filter-select {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 13px;
        background: #ffffff;
        min-width: 150px;
    }

    .pit-badge {
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        display: inline-block;
    }

    .pit-east1 { background-color: #e3f2fd; color: #1976d2; }
    .pit-west { background-color: #fff3e0; color: #f57c00; }
    .pit-efg { background-color: #e8f5e9; color: #388e3c; }
    .pit-east2 { background-color: #f3e5f5; color: #7b1fa2; }
    .pit-least { background-color: #fce4ec; color: #c2185b; }
    .pit-k { background-color: #e0f2f1; color: #00796b; }

    /* Responsive */
    @media (max-width: 1200px) {
        .charts-container {
            grid-template-columns: 1fr;
        }

        .traffic-charts-grid {
            grid-template-columns: 1fr;
        }

        .traffic-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .dashboard-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .stat-value {
            font-size: 32px;
        }

        .traffic-stats-grid {
            grid-template-columns: 1fr;
        }

        .filter-group {
            flex-direction: column;
        }

        .filter-select {
            width: 100%;
        }
    }
</style>
@endsection

@section('content')
<div class="container-fluid" style="max-width: 1400px; margin: 0 auto;">
    <!-- Header -->
    <div class="dashboard-header">
        <div class="dashboard-title">
            <h1>{{ $title }}</h1>
            <p>Detailed Performance Analysis & Historical Metrics</p>
        </div>
        <a href="{{ route('score-card.index') }}" class="btn-back">
            <i class="fa fa-arrow-left"></i> Back to Overview
        </a>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <!-- Daily -->
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Daily Performance</span>
                <div class="stat-icon icon-day">
                    <i class="fa fa-calendar-day"></i>
                </div>
            </div>
            <div class="stat-value">
                98 <span class="stat-unit">%</span>
            </div>
            <div class="stat-trend trend-up">
                <i class="fa fa-arrow-up"></i> 2.5% vs yesterday
            </div>
        </div>

        <!-- Weekly -->
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Weekly Average</span>
                <div class="stat-icon icon-week">
                    <i class="fa fa-calendar-week"></i>
                </div>
            </div>
            <div class="stat-value">
                95 <span class="stat-unit">%</span>
            </div>
            <div class="stat-trend trend-up">
                <i class="fa fa-arrow-up"></i> 1.2% vs last week
            </div>
        </div>

        <!-- Monthly -->
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Monthly Average</span>
                <div class="stat-icon icon-month">
                    <i class="fa fa-calendar-alt"></i>
                </div>
            </div>
            <div class="stat-value">
                92 <span class="stat-unit">%</span>
            </div>
            <div class="stat-trend trend-down">
                <i class="fa fa-arrow-down"></i> 0.8% vs last month
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="charts-container">
        <!-- Main Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Performance Trend (Monthly)</h3>
                <select class="form-select" style="width: auto;">
                    <option>Last 12 Months</option>
                    <option>Year to Date</option>
                    <option>Last Quarter</option>
                </select>
            </div>
            <div style="height: 350px;">
                <canvas id="mainChart"></canvas>
            </div>
        </div>

        <!-- Top Contractors -->
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Top Contractors</h3>
            </div>
            <table class="contractor-table">
                <thead>
                    <tr>
                        <th>Contractor</th>
                        <th>Score</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="contractor-row">
                        <td>
                            <div style="font-weight: 600;">PT BUMA</div>
                            <small class="text-muted">Mining Services</small>
                        </td>
                        <td>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 700;">98%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill bg-success" style="width: 98%"></div>
                            </div>
                        </td>
                        <td><span class="badge bg-success">Excellence</span></td>
                    </tr>
                    <tr class="contractor-row">
                        <td>
                            <div style="font-weight: 600;">PT KDC</div>
                            <small class="text-muted">Mining Services</small>
                        </td>
                        <td>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 700;">95%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill bg-success" style="width: 95%"></div>
                            </div>
                        </td>
                        <td><span class="badge bg-success">Good</span></td>
                    </tr>
                    <tr class="contractor-row">
                        <td>
                            <div style="font-weight: 600;">PT FAD</div>
                            <small class="text-muted">Mining Services</small>
                        </td>
                        <td>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 700;">88%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill bg-warning" style="width: 88%"></div>
                            </div>
                        </td>
                        <td><span class="badge bg-warning">Warning</span></td>
                    </tr>
                    <tr class="contractor-row">
                        <td>
                            <div style="font-weight: 600;">PT MTL</div>
                            <small class="text-muted">Mining Services</small>
                        </td>
                        <td>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 700;">76%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill bg-danger" style="width: 76%"></div>
                            </div>
                        </td>
                        <td><span class="badge bg-danger">Critical</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Breakdown Detail Section -->
    {{-- <div class="breakdown-section">
        <div class="breakdown-header">
            <h3 class="breakdown-title">Performance Breakdown</h3>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <button class="tab-btn active" onclick="showTab('daily', this)">
                <i class="fa fa-calendar-day"></i> Daily
            </button>
            <button class="tab-btn" onclick="showTab('weekly', this)">
                <i class="fa fa-calendar-week"></i> Weekly
            </button>
            <button class="tab-btn" onclick="showTab('monthly', this)">
                <i class="fa fa-calendar-alt"></i> Monthly
            </button>
        </div>

        <!-- Daily Breakdown -->
        <div id="daily-tab" class="tab-content active">
            <table class="breakdown-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Today</th>
                        <th>Yesterday</th>
                        <th>Change</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="breakdown-row">
                        <td class="metric-name">Safety Score</td>
                        <td class="metric-value">98%</td>
                        <td>96%</td>
                        <td><span class="metric-change change-positive">+2.0%</span></td>
                        <td><span class="badge bg-success">Excellent</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">Quality Score</td>
                        <td class="metric-value">97%</td>
                        <td>95%</td>
                        <td><span class="metric-change change-positive">+2.0%</span></td>
                        <td><span class="badge bg-success">Excellent</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">Productivity</td>
                        <td class="metric-value">96%</td>
                        <td>94%</td>
                        <td><span class="metric-change change-positive">+2.0%</span></td>
                        <td><span class="badge bg-success">Good</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">On-Time Delivery</td>
                        <td class="metric-value">99%</td>
                        <td>98%</td>
                        <td><span class="metric-change change-positive">+1.0%</span></td>
                        <td><span class="badge bg-success">Excellent</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">Cost Efficiency</td>
                        <td class="metric-value">95%</td>
                        <td>96%</td>
                        <td><span class="metric-change change-negative">-1.0%</span></td>
                        <td><span class="badge bg-warning">Good</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">Overall Performance</td>
                        <td class="metric-value">98%</td>
                        <td>95.5%</td>
                        <td><span class="metric-change change-positive">+2.5%</span></td>
                        <td><span class="badge bg-success">Excellent</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Weekly Breakdown -->
        <div id="weekly-tab" class="tab-content">
            <table class="breakdown-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>This Week</th>
                        <th>Last Week</th>
                        <th>Change</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="breakdown-row">
                        <td class="metric-name">Safety Score</td>
                        <td class="metric-value">96%</td>
                        <td>94%</td>
                        <td><span class="metric-change change-positive">+2.0%</span></td>
                        <td><span class="badge bg-success">Excellent</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">Quality Score</td>
                        <td class="metric-value">95%</td>
                        <td>94%</td>
                        <td><span class="metric-change change-positive">+1.0%</span></td>
                        <td><span class="badge bg-success">Good</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">Productivity</td>
                        <td class="metric-value">94%</td>
                        <td>93%</td>
                        <td><span class="metric-change change-positive">+1.0%</span></td>
                        <td><span class="badge bg-success">Good</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">On-Time Delivery</td>
                        <td class="metric-value">97%</td>
                        <td>96%</td>
                        <td><span class="metric-change change-positive">+1.0%</span></td>
                        <td><span class="badge bg-success">Excellent</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">Cost Efficiency</td>
                        <td class="metric-value">93%</td>
                        <td>94%</td>
                        <td><span class="metric-change change-negative">-1.0%</span></td>
                        <td><span class="badge bg-warning">Good</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">Overall Performance</td>
                        <td class="metric-value">95%</td>
                        <td>93.8%</td>
                        <td><span class="metric-change change-positive">+1.2%</span></td>
                        <td><span class="badge bg-success">Good</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Monthly Breakdown -->
        <div id="monthly-tab" class="tab-content">
            <table class="breakdown-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>This Month</th>
                        <th>Last Month</th>
                        <th>Change</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="breakdown-row">
                        <td class="metric-name">Safety Score</td>
                        <td class="metric-value">94%</td>
                        <td>95%</td>
                        <td><span class="metric-change change-negative">-1.0%</span></td>
                        <td><span class="badge bg-success">Good</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">Quality Score</td>
                        <td class="metric-value">93%</td>
                        <td>93%</td>
                        <td><span class="metric-change change-neutral">0.0%</span></td>
                        <td><span class="badge bg-success">Good</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">Productivity</td>
                        <td class="metric-value">92%</td>
                        <td>91%</td>
                        <td><span class="metric-change change-positive">+1.0%</span></td>
                        <td><span class="badge bg-success">Good</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">On-Time Delivery</td>
                        <td class="metric-value">95%</td>
                        <td>96%</td>
                        <td><span class="metric-change change-negative">-1.0%</span></td>
                        <td><span class="badge bg-success">Good</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">Cost Efficiency</td>
                        <td class="metric-value">91%</td>
                        <td>92%</td>
                        <td><span class="metric-change change-negative">-1.0%</span></td>
                        <td><span class="badge bg-warning">Good</span></td>
                    </tr>
                    <tr class="breakdown-row">
                        <td class="metric-name">Overall Performance</td>
                        <td class="metric-value">92%</td>
                        <td>92.8%</td>
                        <td><span class="metric-change change-negative">-0.8%</span></td>
                        <td><span class="badge bg-success">Good</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div> --}}

    <!-- Traffic Management Section -->
    <div class="traffic-section">
        <div class="breakdown-section">
            <div class="breakdown-header">
                <h3 class="breakdown-title">Traffic Management</h3>
            </div>

            <!-- Traffic Stats -->
            <div class="traffic-stats-grid">
                <div class="traffic-stat-card">
                    <div class="traffic-stat-value">24</div>
                    <div class="traffic-stat-label">Total Jalan</div>
                </div>
                <div class="traffic-stat-card">
                    <div class="traffic-stat-value">11,189</div>
                    <div class="traffic-stat-label">Total Panjang (m)</div>
                </div>
                <div class="traffic-stat-card">
                    <div class="traffic-stat-value">112</div>
                    <div class="traffic-stat-label">Total Segmen</div>
                </div>
                <div class="traffic-stat-card">
                    <div class="traffic-stat-value">100%</div>
                    <div class="traffic-stat-label">Kesesuaian Standar</div>
                </div>
            </div>

            <!-- Traffic Charts -->
            <div class="traffic-charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Distribusi Kategori Jalan</h3>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Distribusi per Pit</h3>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="pitChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Traffic Table -->
            <div class="traffic-table-card">
                <div class="chart-header">
                    <h3 class="chart-title">Detail Jalan</h3>
                    <div class="filter-group">
                        <select class="filter-select" id="filterCategory">
                            <option value="">All Categories</option>
                            <option value="Main Road">Main Road</option>
                            <option value="Dump Road">Dump Road</option>
                            <option value="In-Pit Road">In-Pit Road</option>
                        </select>
                        <select class="filter-select" id="filterPit">
                            <option value="">All Pits</option>
                            <option value="EAST 1">EAST 1</option>
                            <option value="WEST">WEST</option>
                            <option value="EFG">EFG</option>
                            <option value="EAST 2">EAST 2</option>
                            <option value="L EAST">L EAST</option>
                            <option value="K">K</option>
                        </select>
                    </div>
                </div>
                <div class="traffic-table-wrapper">
                    <table class="traffic-table" id="trafficTable">
                        <thead>
                            <tr>
                                <th>Kategori Jalan</th>
                                <th>Pit</th>
                                <th>Nama Jalan</th>
                                <th>Panjang (m)</th>
                                <th>Segmen</th>
                                <th>Road Width (m)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Main Road</td>
                                <td><span class="pit-badge pit-east1">EAST 1</span></td>
                                <td>AS SALAM</td>
                                <td>929</td>
                                <td>9</td>
                                <td>24</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>Main Road</td>
                                <td><span class="pit-badge pit-west">WEST</span></td>
                                <td>AL MUMIN</td>
                                <td>1,303</td>
                                <td>13</td>
                                <td>24</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>Main Road</td>
                                <td><span class="pit-badge pit-efg">EFG</span></td>
                                <td>AL LATIF</td>
                                <td>524</td>
                                <td>5</td>
                                <td>24</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>Main Road</td>
                                <td><span class="pit-badge pit-east2">EAST 2</span></td>
                                <td>SYUKUR</td>
                                <td>329</td>
                                <td>3</td>
                                <td>24</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>Dump Road</td>
                                <td><span class="pit-badge pit-least">L EAST</span></td>
                                <td>IN DISP LWEST</td>
                                <td>247</td>
                                <td>2</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>Dump Road</td>
                                <td><span class="pit-badge pit-east2">EAST 2</span></td>
                                <td>IN DISP OPDW EXTEND</td>
                                <td>297</td>
                                <td>3</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>Dump Road</td>
                                <td><span class="pit-badge pit-east2">EAST 2</span></td>
                                <td>IN DISP OPDW EXTEND 2</td>
                                <td>439</td>
                                <td>4</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>Dump Road</td>
                                <td><span class="pit-badge pit-east1">EAST 1</span></td>
                                <td>IN DISP OPD EAST 1</td>
                                <td>1,069</td>
                                <td>11</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>Dump Road</td>
                                <td><span class="pit-badge pit-west">WEST</span></td>
                                <td>IN DISP OPD WEST</td>
                                <td>297</td>
                                <td>3</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>Dump Road</td>
                                <td><span class="pit-badge pit-west">WEST</span></td>
                                <td>IN DISP BUTTRESS SELATAN</td>
                                <td>353</td>
                                <td>4</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>Dump Road</td>
                                <td><span class="pit-badge pit-efg">EFG</span></td>
                                <td>IN DISP BUTTRESS EFG</td>
                                <td>830</td>
                                <td>8</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>Dump Road</td>
                                <td><span class="pit-badge pit-k">K</span></td>
                                <td>IN DISP BUTTRESS K</td>
                                <td>533</td>
                                <td>5</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>In-Pit Road</td>
                                <td><span class="pit-badge pit-west">WEST</span></td>
                                <td>IN FRONT EX1171</td>
                                <td>613</td>
                                <td>6</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>In-Pit Road</td>
                                <td><span class="pit-badge pit-east2">EAST 2</span></td>
                                <td>IN FRONT EX1181</td>
                                <td>230</td>
                                <td>2</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>In-Pit Road</td>
                                <td><span class="pit-badge pit-west">WEST</span></td>
                                <td>IN FRONT EX1185</td>
                                <td>177</td>
                                <td>2</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>In-Pit Road</td>
                                <td><span class="pit-badge pit-west">WEST</span></td>
                                <td>IN FRONT EX1250</td>
                                <td>224</td>
                                <td>2</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>In-Pit Road</td>
                                <td><span class="pit-badge pit-east1">EAST 1</span></td>
                                <td>IN FRONT EX1260</td>
                                <td>521</td>
                                <td>5</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>In-Pit Road</td>
                                <td><span class="pit-badge pit-east1">EAST 1</span></td>
                                <td>IN FRONT EX1262</td>
                                <td>298</td>
                                <td>3</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>In-Pit Road</td>
                                <td><span class="pit-badge pit-east2">EAST 2</span></td>
                                <td>IN FRONT EX1266</td>
                                <td>534</td>
                                <td>5</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>In-Pit Road</td>
                                <td><span class="pit-badge pit-east1">EAST 1</span></td>
                                <td>IN FRONT EX1280</td>
                                <td>557</td>
                                <td>6</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>In-Pit Road</td>
                                <td><span class="pit-badge pit-efg">EFG</span></td>
                                <td>IN FRONT EX1283</td>
                                <td>225</td>
                                <td>2</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>In-Pit Road</td>
                                <td><span class="pit-badge pit-least">L EAST</span></td>
                                <td>IN FRONT EX1284</td>
                                <td>469</td>
                                <td>5</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>In-Pit Road</td>
                                <td><span class="pit-badge pit-west">WEST</span></td>
                                <td>IN FRONT EX1296</td>
                                <td>522</td>
                                <td>5</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                            <tr>
                                <td>In-Pit Road</td>
                                <td><span class="pit-badge pit-east2">EAST 2</span></td>
                                <td>IN FRONT EX1313</td>
                                <td>180</td>
                                <td>2</td>
                                <td>21</td>
                                <td><span class="badge bg-success">Standar</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Initialize Chart.js
    const ctx = document.getElementById('mainChart').getContext('2d');
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(33, 150, 243, 0.2)');
    gradient.addColorStop(1, 'rgba(33, 150, 243, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Performance Score',
                data: [85, 88, 86, 90, 89, 92, 94, 93, 91, 95, 96, 98],
                borderColor: '#2196F3',
                backgroundColor: gradient,
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#2196F3',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 10,
                    titleFont: {
                        size: 13,
                        weight: '600'
                    },
                    bodyFont: {
                        size: 12
                    },
                    cornerRadius: 6,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    min: 60,
                    max: 100,
                    ticks: {
                        font: {
                            size: 11
                        },
                        color: '#6b7280'
                    },
                    grid: {
                        color: '#f3f4f6'
                    }
                },
                x: {
                    ticks: {
                        font: {
                            size: 11
                        },
                        color: '#6b7280'
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Animate progress bars on load
    document.addEventListener('DOMContentLoaded', function() {
        const progressBars = document.querySelectorAll('.progress-fill');
        progressBars.forEach((bar) => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 300);
        });
    });

    // Tab switching function
    function showTab(tabName, button) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });

        // Remove active class from all buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Show selected tab content
        document.getElementById(tabName + '-tab').classList.add('active');

        // Add active class to clicked button
        button.classList.add('active');
    }

    // Traffic Management Charts
    document.addEventListener('DOMContentLoaded', function() {
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart');
        if (categoryCtx) {
            const categoryGradient = categoryCtx.getContext('2d').createLinearGradient(0, 0, 0, 300);
            categoryGradient.addColorStop(0, 'rgba(33, 150, 243, 0.2)');
            categoryGradient.addColorStop(1, 'rgba(33, 150, 243, 0)');

            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Main Road', 'Dump Road', 'In-Pit Road'],
                    datasets: [{
                        data: [4, 8, 12],
                        backgroundColor: [
                            '#2196F3',
                            '#FF9800',
                            '#4CAF50'
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
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 10,
                            cornerRadius: 6
                        }
                    }
                }
            });
        }

        // Pit Chart
        const pitCtx = document.getElementById('pitChart');
        if (pitCtx) {
            new Chart(pitCtx, {
                type: 'bar',
                data: {
                    labels: ['EAST 1', 'WEST', 'EFG', 'EAST 2', 'L EAST', 'K'],
                    datasets: [{
                        label: 'Jumlah Jalan',
                        data: [5, 7, 3, 5, 2, 1],
                        backgroundColor: [
                            '#2196F3',
                            '#FF9800',
                            '#4CAF50',
                            '#9C27B0',
                            '#E91E63',
                            '#009688'
                        ],
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 10,
                            cornerRadius: 6
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: {
                                    size: 11
                                },
                                color: '#6b7280'
                            },
                            grid: {
                                color: '#f3f4f6'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 11
                                },
                                color: '#6b7280'
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // Table Filter
        const filterCategory = document.getElementById('filterCategory');
        const filterPit = document.getElementById('filterPit');
        const table = document.getElementById('trafficTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

        function filterTable() {
            const categoryValue = filterCategory.value.toLowerCase();
            const pitValue = filterPit.value.toLowerCase();

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const category = row.cells[0].textContent.trim().toLowerCase();
                const pitCell = row.cells[1];
                const pit = pitCell.textContent.trim().toLowerCase();

                const categoryMatch = !categoryValue || category === categoryValue;
                const pitMatch = !pitValue || pit === pitValue;

                if (categoryMatch && pitMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }

        if (filterCategory) {
            filterCategory.addEventListener('change', filterTable);
        }
        if (filterPit) {
            filterPit.addEventListener('change', filterTable);
        }
    });
</script>
@endsection
