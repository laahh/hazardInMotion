@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Hazard Detection - Beraucoal')

@section('css')
<style>
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
    
    /* Map container - menggunakan class dari template */
    #hazardMap {
        width: 100%;
        height: 600px;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
        background-color: #f9fafb;
    }
    
    /* Map container responsive */
    @media (max-width: 1200px) {
        #hazardMap {
            height: 500px;
        }
    }
    
    @media (max-width: 768px) {
        #hazardMap {
            height: 400px;
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
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@8.2.0/ol.css">
<link rel="stylesheet" href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}">
@endsection

@section('content')
<div class="hazard-detection-header">
    <h1 class="hazard-detection-title">Hazard in Motion </h1>
    <p class="hazard-detection-subtitle">Real-time detection and monitoring of safety hazards in operational areas</p>
</div>

<!-- Statistics Cards -->
 {{-- <div class="row">
          <div class="col-12 col-xl-4 d-flex">
             <div class="card rounded-4 w-100">
               <div class="card-body">
                 <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="flex-grow-1">
                      <h2 class="mb-0" style="font-size: 28px; font-weight: 700; color: #111827;">{{ $totalYtdInsiden ?? '10' }}</h2>
                      <p class="mb-0 mt-1" style="font-size: 14px; color: #6b7280; font-weight: 500;">Total YTD Insiden</p>
                    </div>
                    <div class="">
                      <button class="btn btn-link p-0" type="button" style="color: #6b7280; text-decoration: none;">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path d="M10 6C10.5523 6 11 5.55228 11 5C11 4.44772 10.5523 4 10 4C9.44772 4 9 4.44772 9 5C9 5.55228 9.44772 6 10 6Z" fill="currentColor"/>
                          <path d="M10 11C10.5523 11 11 10.5523 11 10C11 9.44772 10.5523 9 10 9C9.44772 9 9 9.44772 9 10C9 10.5523 9.44772 11 10 11Z" fill="currentColor"/>
                          <path d="M10 16C10.5523 16 11 15.5523 11 15C11 14.4477 10.5523 14 10 14C9.44772 14 9 14.4477 9 15C9 15.5523 9.44772 16 10 16Z" fill="currentColor"/>
                        </svg>
                      </button>
                    </div>

                  </div>
                   <div id="chartYtdInsiden" style="margin-top: 20px; margin-bottom: 16px;"></div>
                   <div class="d-flex align-items-center gap-2">
                     <span style="color: #10b981; font-size: 14px; font-weight: 600;">{{ $ytdInsidenChange ?? '12.5' }}%</span>
                     <span style="color: #6b7280; font-size: 14px;">from last month</span>
                   </div>
               </div>
             </div>
          </div>
          
          <div class="col-12 col-xl-8 d-flex">
            <div class="card rounded-4 w-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-around flex-wrap gap-4 p-4">
                  <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <a href="javascript:;" class="mb-2 wh-48 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">camera</i>
                    </a>
                    <h3 class="mb-0">120</h3>
                    <p class="mb-0">CCTV</p>
                  </div>
                  <div class="vr"></div>
                  <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <a href="javascript:;" class="mb-2 wh-48 bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center">
                    <i class="material-icons-outlined">report_problem</i>
                    </a>
                    <h3 class="mb-0">1</h3>
                    <p class="mb-0">Jumlah Insiden</p>
                  </div>
                  <div class="vr"></div>
                  <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <a href="javascript:;" class="mb-2 wh-48 bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center">
                     <i class="material-icons-outlined">report_problem</i>
                    </a>
                    <h3 class="mb-0">2</h3>
                    <p class="mb-0">Golden Rules</p>
                  </div>
                  <div class="vr"></div>
                  
                  <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <a href="javascript:;" class="mb-2 wh-48 bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">payment</i>
                    </a>
                    <h3 class="mb-0">80%</h3>
                    <p class="mb-0">Closing Hazard</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
</div> --}}

@php
    $initialCriticalCoveragePercentage = $criticalCoveragePercentage ?? 95.1;
@endphp
<script>
    window.initialCriticalCoveragePercentage = {{ $initialCriticalCoveragePercentage }};
    window.chart2InitialValue = window.initialCriticalCoveragePercentage;
</script>

<div class="row">
    
          <div class="col-12 col-xl-5 col-xxl-4 d-flex">
            <div class="card rounded-4 w-100 shadow-none bg-transparent border-0">
               <div class="card-body p-0">
                 <div class="row g-4">
                    <div class="col-12 col-xl-6 d-flex">
                      <div class="card mb-0 rounded-4 w-100">
                       <div class="card-body">
                         <div class="d-flex align-items-start justify-content-between mb-3">
                           <div class="">
                             <h4 class="mb-0">{{ number_format($totalYtdInsiden ?? 19) }}</h4>
                             <p class="mb-0">Total YTD Insiden BMO 2</p>
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
                   <div class="col-12 col-xl-6 d-flex">
                    <div class="card mb-0 rounded-4 w-100">
                     <div class="card-body">
                      <div class="d-flex align-items-start justify-content-between mb-1">
                         <div class="">
                           <h4 class="mb-0" id="criticalAreaCardCount">{{ number_format($criticalCoverageCctv ?? 0) }}</h4>
                           <p class="mb-0">CCTV Area Kritis</p>
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
                         <div id="chart2"></div>
                       </div>
                       <div class="text-center">
                         <p class="mb-0" id="criticalCoverageDescription">{{ number_format($criticalCoveragePercentage ?? 95.1, 1) }}% area kritis ter-cover CCTV</p>
                       </div>
                     </div>
                    </div>
                 </div>
                   <div class="col-12 col-xl-12">
                    <div class="card rounded-4 mb-0">
                      <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-2">
                           <div class="">
                             <h2 class="mb-0">{{ number_format($resolvedHazards ?? count(array_filter($hazardDetections ?? [], function($h) { return $h['status'] === 'resolved'; }))) }}</h2>
                           </div>
                           <div class="">
                             <p class="dash-lable d-flex align-items-center gap-1 rounded mb-0 bg-success text-success bg-opacity-10"><span class="material-icons-outlined fs-6">arrow_upward</span>{{ $resolvedHazardsChange ?? '8.6' }}%</p>
                           </div>
                         </div>
                         <p class="mb-0">Resolved Hazards This Year</p>
                          <div class="mt-4">
                            @php
                              $totalHazards = count($hazardDetections ?? []);
                              $resolvedCount = count(array_filter($hazardDetections ?? [], function($h) { return $h['status'] === 'resolved'; }));
                              $resolvedPercentage = $totalHazards > 0 ? round(($resolvedCount / $totalHazards) * 100) : 0;
                              $remaining = $totalHazards - $resolvedCount;
                            @endphp
                            <p class="mb-2 d-flex align-items-center justify-content-between">{{ $remaining }} left to Goal<span class="">{{ $resolvedPercentage }}%</span></p>
                            <div class="progress w-100" style="height: 7px;">
                              <div class="progress-bar bg-primary" style="width: {{ $resolvedPercentage }}%"></div>
                            </div>
                          </div>
                           
                      </div>
                    </div>
                   </div>

                   

                 </div><!--end row-->
               </div>
            </div>  
          </div> 
          <div class="col-12 col-xl-7 col-xxl-8 d-flex">
            <div class="card w-100 rounded-4">
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
                  <div class="d-flex flex-column flex-lg-row align-items-start justify-content-around border p-3 rounded-4 mt-3 gap-3">
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
                    <div class="d-flex align-items-center gap-4 cctv-stat-card" id="cctvStatCard" style="cursor: pointer; padding: 8px; border-radius: 8px; transition: all 0.2s;" 
                         title="Klik untuk melihat detail CCTV">
                      <div class="">
                        <p class="mb-0 data-attributes">
                          <span id="donutCctv"
                            data-peity='{ "fill": ["#6f42c1", "rgb(0 0 0 / 10%)"], "innerRadius": 32, "radius": 40 }'>0/100</span>
                        </p>
                      </div>
                      <div class="">
                        <p class="mb-1 fs-6 fw-bold">CCTV</p>
                        <h2 class="mb-0" id="statCctvCount">{{ number_format($yearlyHazards ?? 1461 ) }}</h2>
                        <p class="mb-0"><span class="text-success me-2 fw-medium" id="statCctvChange">{{ $yearlyChange ?? '24.9' }}%</span><span id="statCctvText">{{ $yearlyCount ?? '267' }} hazards</span></p>
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
                        <h2 class="mb-0" id="statInsidenCount">{{ number_format($yearlyHazards ?? 19) }}</h2>
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
                        <p class="mb-1 fs-6 fw-bold">GR BMO2</p>
                        <h2 class="mb-0" id="statGrCount">{{ number_format($yearlyHazards ?? 17) }}</h2>
                        <p class="mb-0"><span class="text-success me-2 fw-medium" id="statGrChange">{{ $yearlyChange ?? '24.9' }}%</span><span id="statGrText">{{ $yearlyCount ?? '267' }} hazards</span></p>
                      </div>
                    </div>

                    
                  </div>
               </div>
            </div>  
          </div> 
        </div><!--end row-->

<!-- Filter Controls -->


{{-- <div class="filter-controls">
    <div class="row">
        <div class="col-md-3">
            <label for="statusFilter" class="form-label">Status Filter</label>
            <select id="statusFilter" class="form-select">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="resolved">Resolved</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="severityFilter" class="form-label">Severity Filter</label>
            <select id="severityFilter" class="form-select">
                <option value="all">All Severity</option>
                <option value="critical">Critical</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="typeFilter" class="form-label">Type Filter</label>
            <select id="typeFilter" class="form-select">
                <option value="all">All Types</option>
                <option value="Personnel in Restricted Zone">Personnel Violation</option>
                <option value="Equipment Violation">Equipment Violation</option>
                <option value="Safety Protocol Violation">Safety Protocol</option>
                <option value="Unauthorized Access">Unauthorized Access</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="wmsServerSelect" class="form-label">WMS Server</label>
            <select id="wmsServerSelect" class="form-select">
                <option value="smo">SMO Block B1</option>
                <option value="smoA">SMO Block A</option>
                <option value="smoBEastWest">SMO Block B East-West</option>
                <option value="bmo">BMO Block 1-4</option>
                <option value="bmo56">BMO Block 5-6</option>
                <option value="bmo7">BMO Block 7</option>
                <option value="bmo8">BMO Block 8</option>
                <option value="bmo9">BMO Block 9</option>
                <option value="bmo10">BMO Block 10</option>
                <option value="bmoParapatan">BMO Block Parapatan</option>
                <option value="gurimbang">Gurimbang</option>
                <option value="khdtk">KHDTK</option>
                <option value="punan">Punan</option>
                <option value="lati">Lati</option>
            </select>
        </div>
    </div>
    <div class="row mt-2">
        <div class="col-md-4">
            <label for="layerSelect" class="form-label">WMS Layer</label>
            <select id="layerSelect" class="form-select">
                <option value="0">Loading...</option>
            </select>
        </div>
        <div class="col-md-4">
            <label for="projectionSelect" class="form-label">Projection</label>
            <select id="projectionSelect" class="form-select">
                <option value="EPSG:3857">Web Mercator (EPSG:3857)</option>
                <option value="EPSG:4326">WGS84 (EPSG:4326)</option>
            </select>
        </div>
    </div>
</div> --}}

<!-- GeoJSON Layer Controls -->
{{-- <div class="card rounded-4 mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-12">
                <label class="form-label fw-bold mb-3">GeoJSON Layers:</label>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="showAreaKerjaBmo2Pama" checked>
                            <label class="form-check-label" for="showAreaKerjaBmo2Pama">
                                Area Kerja BMO2 PAMA
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="showAreaCctvBmo2Pama" checked>
                            <label class="form-check-label" for="showAreaCctvBmo2Pama">
                                Area CCTV BMO2 PAMA
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="showDifferenceBmo2Pama" checked>
                            <label class="form-check-label" for="showDifferenceBmo2Pama">
                                Difference BMO2 PAMA
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="showSymmetricalDifferenceBmo2Pama" checked>
                            <label class="form-check-label" for="showSymmetricalDifferenceBmo2Pama">
                                Symmetrical Difference BMO2 PAMA
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3 mt-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="showIntersectionBmo2Pama" checked>
                            <label class="form-check-label" for="showIntersectionBmo2Pama">
                                Intersection BMO2 PAMA
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> --}}

<!-- Main Content -->
<div class="row">
    <!-- Hazard List / CCTV Stream -->
    <div class="col-12 col-xl-5 d-flex">
        <div class="card rounded-4 w-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3 flex-column flex-lg-row gap-2">
                    <div class="">
                        <h5 class="mb-0 fw-bold" id="cardTitle">Laporan Hazard Beats</h5>
                    </div>
                    <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
                        <div class="d-flex align-items-center gap-2">
                            <label for="viewSelector" class="form-label mb-0 text-muted small">Tampilan</label>
                            <select id="viewSelector" class="form-select form-select-sm" onchange="switchView(this.value)">
                                <option value="hazard">Hazard</option>
                                <option value="cctv">CCTV</option>
                                <option value="insiden">Insiden</option>
                                <option value="python">Python App</option>
                            </select>
                        </div>
                    </div>
                </div>
                <!-- Hazard List View -->
                <div class="hazards-list" id="hazardListView" style="max-height: 600px; overflow-y: auto;">
                    <div class="d-flex flex-column gap-4">
                        @foreach($hazardDetections as $hazard)
                        @php
                            $severity = $hazard['severity'] ?? 'medium';
                            $statusClass = $hazard['status'] === 'active' ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success';
                        @endphp
                        <div class="hazard-item {{ $hazard['status'] === 'active' ? 'active' : 'resolved' }}" 
                             data-hazard-id="{{ $hazard['id'] }}"
                             data-lat="{{ $hazard['location']['lat'] ?? '' }}"
                             data-lng="{{ $hazard['location']['lng'] ?? '' }}">
                            <div class="hazard-card hazard-card-{{ $severity }}">
                                <div class="hazard-card-header">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="hazard-pill hazard-pill-{{ $severity }}">
                                            <i class="material-icons-outlined" style="font-size: 16px;">bolt</i>
                                            {{ ucfirst($severity) }}
                                        </span>
                                        <span class="badge {{ $statusClass }}">
                                            <i class="material-icons-outlined me-1" style="font-size: 16px;">lens</i>{{ ucfirst($hazard['status']) }}
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        <i class="material-icons-outlined me-1" style="font-size: 16px;">schedule</i>
                                        {{ $hazard['detected_at'] }}
                                    </small>
                                </div>
                                <div class="d-flex flex-column flex-md-row gap-3 mt-3">
                                    <div class="position-relative" style="width: 120px; height: 120px; flex-shrink: 0;">
                                        @php
                                            $photoUrl = isset($hazard['url_photo']) && $hazard['url_photo'] ? $hazard['url_photo'] : null;
                                            $hasPhoto = !empty($photoUrl);
                                            $originalId = isset($hazard['original_id']) ? $hazard['original_id'] : null;
                                        @endphp
                                        @if($hasPhoto)
                                        <div class="hazard-photo-container" 
                                             data-hazard-id="{{ $hazard['id'] }}"
                                             data-photo-url="{{ $photoUrl }}"
                                             data-original-id="{{ $originalId }}"
                                             style="width: 100%; height: 100%; position: relative; background: #f8f9fa; border-radius: 8px; cursor: pointer;"
                                             onclick="viewHazardDetails('{{ $hazard['id'] }}')">
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                                                <div class="text-center">
                                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                        <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <p class="mt-1 mb-0 small text-muted" style="font-size: 10px;">Memuat foto...</p>
                                                </div>
                                            </div>
                                        </div>
                                        @else
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center rounded bg-light" onclick="viewHazardDetails('{{ $hazard['id'] }}')" style="cursor: pointer;">
                                                <i class="material-icons-outlined text-muted" style="font-size: 32px;">image_not_supported</i>
                                            </div>
                                        @endif
                                        @if($hazard['status'] === 'active')
                                            <span class="position-absolute top-0 end-0 badge bg-danger" style="font-size: 10px; z-index: 1;">Active</span>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-2">{{ $hazard['type'] }}</h6>
                                        <p class="mb-3 text-muted" style="font-size: 14px;">{{ $hazard['description'] }}</p>
                                        <div class="hazard-card-meta">
                                            <div>
                                                <span>Location</span>
                                                <strong>{{ $hazard['zone'] ?? 'Unknown' }}</strong>
                                            </div>
                                            @if(isset($hazard['personnel_name']))
                                            <div>
                                                <span>Personnel</span>
                                                <strong>{{ $hazard['personnel_name'] }}</strong>
                                            </div>
                                            @endif
                                            @if(isset($hazard['equipment_id']))
                                            <div>
                                                <span>Equipment</span>
                                                <strong>{{ $hazard['equipment_id'] }}</strong>
                                            </div>
                                            @endif
                                            <div>
                                                <span>CCTV</span>
                                                <strong>{{ $hazard['cctv_id'] }}</strong>
                                            </div>
                                        </div>
                                        <div class="hazard-card-actions d-flex flex-wrap gap-2 mt-3">
                                            <button class="btn btn-sm btn-primary" onclick="viewHazardDetails('{{ $hazard['id'] }}')">
                                                <i class="material-icons-outlined me-1" style="font-size: 16px;">visibility</i>
                                                View Details
                                            </button>
                                            @if($hazard['status'] === 'active')
                                            <button class="btn btn-sm btn-outline-success" onclick="resolveHazard('{{ $hazard['id'] }}')">
                                                <i class="material-icons-outlined me-1" style="font-size: 16px;">check_circle</i>
                                                Resolve
                                            </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="hazard-card-footer">
                                    <span class="hazard-chip">
                                        <i class="material-icons-outlined me-1" style="font-size: 14px;">map</i>
                                        {{ $hazard['site'] ?? 'Unknown Site' }}
                                    </span>
                                    @if(isset($hazard['nama_goldenrule']))
                                    <span class="hazard-chip">
                                        <i class="material-icons-outlined me-1" style="font-size: 14px;">rule</i>
                                        {{ $hazard['nama_goldenrule'] }}
                                    </span>
                                    @endif
                                    @if(isset($hazard['nama_kategori']))
                                    <span class="hazard-chip">
                                        <i class="material-icons-outlined me-1" style="font-size: 14px;">label</i>
                                        {{ $hazard['nama_kategori'] }}
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <!-- CCTV Stream List View -->
                <div class="cctv-list" id="cctvListView" style="max-height: 600px; overflow-y: auto; display: none;">
                    <div class="d-flex flex-column gap-3" id="cctvStreamContainer">
                        <!-- CCTV streams will be loaded here via JavaScript -->
                    </div>
                </div>
                <!-- Insiden List View -->
                <div class="insiden-list" id="insidenListView" style="max-height: 600px; overflow-y: auto; display: none;">
                    <div class="d-flex flex-column gap-3">
                        @forelse ($insidenGroups as $insiden)
                            <div class="border rounded-4 p-3 bg-white shadow-sm" data-no-kecelakaan="{{ $insiden['no_kecelakaan'] }}">
                                <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
                                    <div>
                                        <h6 class="mb-1 fw-bold">{{ $insiden['no_kecelakaan'] }}</h6>
                                        <div class="text-muted small">
                                            {{ $insiden['site'] ?? 'Site tidak diketahui' }} • {{ $insiden['lokasi'] ?? '-' }}
                                        </div>
                                        <div class="text-muted small">
                                            {{ $insiden['tanggal'] ? \Carbon\Carbon::parse($insiden['tanggal'])->format('d M Y') : '-' }} • Status LPI: {{ $insiden['status_lpi'] ?? '-' }}
                                        </div>
                                    </div>
                                    <div class="text-lg-end">
                                        <span class="badge bg-primary bg-opacity-10 text-primary me-1">Layer: {{ $insiden['layer'] ?? '-' }}</span>
                                        <span class="badge bg-info bg-opacity-10 text-info me-1">{{ $insiden['jenis_item_ipls'] ?? 'Item tidak ada' }}</span>
                                        <span class="badge bg-success bg-opacity-10 text-success">Kategori: {{ $insiden['kategori'] ?? '-' }}</span>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="mb-2 text-muted small">
                                        {{ data_get($insiden, 'items.0.keterangan_layer') ?? data_get($insiden, 'items.0.detail_layer') ?? 'Tidak ada keterangan layer' }}
                                    </p>
                                    <div class="d-flex flex-wrap gap-2 small text-muted">
                                        <span class="badge rounded-pill bg-light text-dark">Detail Layer: {{ data_get($insiden, 'items.0.detail_layer') ?? '-' }}</span>
                                        <span class="badge rounded-pill bg-light text-dark">Klasifikasi: {{ data_get($insiden, 'items.0.klasifikasi_layer') ?? '-' }}</span>
                                        <span class="badge rounded-pill bg-light text-dark">Lokasi Spesifik: {{ data_get($insiden, 'items.0.lokasi_spesifik') ?? '-' }}</span>
                                        <span class="badge rounded-pill bg-light text-dark">Perusahaan: {{ data_get($insiden, 'items.0.perusahaan') ?? '-' }}</span>
                                    </div>
                                    <div class="mt-3 d-flex flex-wrap gap-2">
                                        <button class="btn btn-sm btn-outline-primary" onclick="openInsidenModal('{{ $insiden['no_kecelakaan'] }}')">
                                            <i class="material-icons-outlined me-1" style="font-size: 16px;">list</i>
                                            Detail Insiden
                                        </button>
                                        @if(!empty($insiden['latitude']) && !empty($insiden['longitude']))
                                            <button class="btn btn-sm btn-outline-success" onclick="focusInsidenOnMap('{{ $insiden['no_kecelakaan'] }}')">
                                                <i class="material-icons-outlined me-1" style="font-size: 16px;">map</i>
                                                Lihat di Map
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">
                                Tidak ada data insiden tersedia.
                            </div>
                        @endforelse
                    </div>
                </div>
                <!-- Python App View -->
                <div class="python-app-view" id="pythonAppView" style="max-height: 600px; overflow-y: auto; display: none;">
                    <div class="d-flex flex-column gap-3">
                        <div class="border rounded-4 p-3 bg-white shadow-sm">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="mb-0 fw-bold">
                                    <i class="material-icons-outlined me-2">code</i>
                                    Python Application
                                </h6>
                                <button class="btn btn-sm btn-outline-primary" onclick="refreshPythonApp()">
                                    <i class="material-icons-outlined me-1" style="font-size: 16px;">refresh</i>
                                    Refresh
                                </button>
                            </div>
                            <div class="position-relative" style="width: 100%; height: 550px; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                                <iframe 
                                    id="pythonAppFrame" 
                                    src="{{ config('app.python_app_url', 'http://localhost:5000') }}" 
                                    style="width: 100%; height: 100%; border: none;"
                                    allowfullscreen
                                    loading="lazy">
                                </iframe>
                                <div id="pythonAppLoading" class="position-absolute top-50 start-50 translate-middle text-center" style="display: none;">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 mb-0 text-muted">Memuat aplikasi Python...</p>
                                </div>
                                <div id="pythonAppError" class="position-absolute top-50 start-50 translate-middle text-center" style="display: none; width: 90%;">
                                    <div class="alert alert-warning">
                                        <i class="material-icons-outlined" style="font-size: 48px; color: #f59e0b;">warning</i>
                                        <h6 class="mt-3">Tidak dapat memuat aplikasi Python</h6>
                                        <p class="mb-2 text-muted small">Pastikan aplikasi Python berjalan di <code>http://localhost:5000</code></p>
                                        <button class="btn btn-sm btn-primary" onclick="refreshPythonApp()">
                                            <i class="material-icons-outlined me-1" style="font-size: 16px;">refresh</i>
                                            Coba Lagi
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map -->
    <div class="col-12 col-xl-7 d-flex">
        <div class="card rounded-4 w-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="">
                        <h5 class="mb-0 fw-bold">Hazard Location Map</h5>
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-2">
                            <label for="siteFilter" class="form-label mb-0 text-muted small">
                                {{-- <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">filter_list</i> --}}
                                Filter
                            </label>
                            <select id="siteFilter" class="form-select form-select-sm" style="min-width: 220px;">
                                <option value="">Semua Site</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="position-relative">
                    <div id="hazardMap"></div>
                    <div id="popup" class="ol-popup">
                        <a href="#" id="popup-closer" class="ol-popup-closer"></a>
                        <div id="popup-content"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk Insiden Pelaporan CCTV -->
<div class="modal fade" id="cctvIncidentsModal" tabindex="-1" aria-labelledby="cctvIncidentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cctvIncidentsModalLabel">Hazard Pelaporan CCTV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="incidentsModalContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Memuat data insiden...</p>
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

<!-- Modal untuk Detail Hazard -->
<div class="modal fade" id="hazardDetailModal" tabindex="-1" aria-labelledby="hazardDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="hazardDetailModalLabel">Detail Hazard</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="hazardDetailContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk Detail Insiden -->
<div class="modal fade" id="insidenDetailModal" tabindex="-1" aria-labelledby="insidenDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="insidenDetailModalLabel">Detail Insiden</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="insidenDetailContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk PJA & Laporan -->
<div class="modal fade" id="cctvPjaModal" tabindex="-1" aria-labelledby="cctvPjaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cctvPjaModalLabel">PJA & Laporan di Lokasi CCTV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="pjaModalContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Memuat data PJA...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk Detail Total CCTV -->
<div class="modal fade" id="totalCctvModal" tabindex="-1" aria-labelledby="totalCctvModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content rounded-0">
      <div class="modal-header border-bottom">
        <div class="">
          <h5 class="modal-title fw-bold" id="totalCctvModalLabel">Detail Total CCTV</h5>
          <p class="mb-0 text-muted small">Statistik & Analisis CCTV berdasarkan Filter</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        {{-- Filter Section --}}
        <div class="card mb-4">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="">
                <h5 class="mb-0 fw-bold">Filter Data</h5>
                <small class="text-muted">Pilih perusahaan dan site untuk memfilter data</small>
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="btnResetFilter">
                <i class="material-icons-outlined me-1" style="font-size: 16px;">refresh</i>
                Reset
              </button>
            </div>
            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label for="filterCompany" class="form-label fw-semibold mb-2">
                  <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">business</i>
                  Filter Perusahaan
                </label>
                <select class="form-select" id="filterCompany">
                  <option value="__all__">Semua Perusahaan</option>
                </select>
              </div>
              <div class="col-12 col-md-6">
                <label for="filterSite" class="form-label fw-semibold mb-2">
                  <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">location_on</i>
                  Filter Site
                </label>
                <select class="form-select" id="filterSite">
                  <option value="__all__">Semua Site</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        {{-- KPI Summary Cards --}}
        <div class="row mb-4">
          <div class="col-12 d-flex">
            <div class="card rounded-4 w-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <h5 class="mb-0 fw-bold">Statistik CCTV</h5>
                  <span class="badge bg-primary" id="modalCoverageBadge">0% Coverage</span>
                </div>
                <div class="d-flex align-items-center justify-content-around flex-wrap gap-4 p-4">
                  <button type="button" class="btn p-0 border-0 bg-transparent d-flex flex-column align-items-center justify-content-center gap-2" title="Total CCTV Terpasang">
                    <span class="mb-2 wh-48 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center">
                      <span class="material-icons-outlined">videocam</span>
                    </span>
                    <h3 class="mb-0" id="modalTotalCctv">0</h3>
                    <p class="mb-0">Total CCTV Terpasang</p>
                  </button>
                  <div class="vr"></div>
                  <button type="button" class="btn p-0 border-0 bg-transparent d-flex flex-column align-items-center justify-content-center gap-2" title="CCTV Aktif Live View & Connected">
                    <span class="mb-2 wh-48 bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center">
                      <span class="material-icons-outlined">check_circle</span>
                    </span>
                    <h3 class="mb-0" id="modalCctvAktif">0</h3>
                    <p class="mb-0">CCTV Aktif</p>
                    <small class="text-muted">Live View & Connected</small>
                  </button>
                  <div class="vr"></div>
                  <button type="button" class="btn p-0 border-0 bg-transparent d-flex flex-column align-items-center justify-content-center gap-2" title="Kondisi Baik">
                    <span class="mb-2 wh-48 bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center">
                      <span class="material-icons-outlined">verified</span>
                    </span>
                    <h3 class="mb-0" id="modalCctvKondisiBaik">0</h3>
                    <p class="mb-0">Kondisi Baik</p>
                  </button>
                  <div class="vr"></div>
                  <button type="button" class="btn p-0 border-0 bg-transparent d-flex flex-column align-items-center justify-content-center gap-2" title="Dengan Auto Alert">
                    <span class="mb-2 wh-48 bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center">
                      <span class="material-icons-outlined">notifications_active</span>
                    </span>
                    <h3 class="mb-0" id="modalCctvAutoAlert">0</h3>
                    <p class="mb-0">Dengan Auto Alert</p>
                  </button>
                  <div class="vr"></div>
                  <button type="button" class="btn p-0 border-0 bg-transparent d-flex flex-column align-items-center justify-content-center gap-2" title="Kondisi CCTV Tidak Baik">
                    <span class="mb-2 wh-48 bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center">
                      <span class="material-icons-outlined">error</span>
                    </span>
                    <h3 class="mb-0" id="modalCctvKondisiTidakBaik">0</h3>
                    <p class="mb-0">Kondisi CCTV Tidak Baik</p>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Area Kritis Overview Section --}}
        <div class="card mb-4">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="">
                <h5 class="mb-0 fw-bold">Overview Area Kritis</h5>
                <small class="text-muted">Statistik area kritis berdasarkan kategori_area_tercapture</small>
              </div>
            </div>
            <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-3 g-3 mb-3">
              <div class="col">
                <div class="card shadow-none border rounded-3 mb-0">
                  <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                      <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="wh-48 d-flex align-items-center bg-danger bg-opacity-10 justify-content-center rounded-circle">
                          <span class="material-icons-outlined text-danger">warning</span>
                        </div>
                        <div class="">
                          <h5 class="mb-0" id="modalJumlahAreaKritis">0</h5>
                          <p class="mb-0">Jumlah Area Kritis</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card shadow-none border rounded-3 mb-0">
                  <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                      <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="wh-48 d-flex align-items-center bg-danger bg-opacity-10 justify-content-center rounded-circle">
                          <span class="material-icons-outlined text-danger">videocam</span>
                        </div>
                        <div class="">
                          <h5 class="mb-0" id="modalCctvAreaKritis">0</h5>
                          <p class="mb-0">CCTV Area Kritis</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card shadow-none border rounded-3 mb-0">
                  <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                      <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="wh-48 d-flex align-items-center bg-success bg-opacity-10 justify-content-center rounded-circle">
                          <span class="material-icons-outlined text-success">check_circle</span>
                        </div>
                        <div class="">
                          <h5 class="mb-0" id="modalCctvAreaNonKritis">0</h5>
                          <p class="mb-0">CCTV Area Non Kritis</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            {{-- Detail Coverage Lokasi --}}
            <div class="card shadow-none border rounded-3 mb-0">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h6 class="mb-0 fw-bold">Detail Coverage Lokasi</h6>
                    <small class="text-muted">Daftar lokasi coverage beserta jumlah CCTV dan status kritis/non kritis</small>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle" data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Export</a></li>
                    </ul>
                  </div>
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                      <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 50%;">Coverage Lokasi</th>
                        <th style="width: 20%;" class="text-end">Jumlah CCTV</th>
                        <th style="width: 25%;" class="text-center">Status</th>
                      </tr>
                    </thead>
                    <tbody id="detailCoverageLokasiTableBody">
                      <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                          <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                          Memuat data...
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Issues/Alert Section --}}
        <div class="card mb-4">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="">
                <h5 class="mb-0 fw-bold">Issues & Alerts</h5>
                <small class="text-muted">CCTV yang memerlukan perhatian</small>
              </div>
            </div>
            <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-4 g-3">
              <div class="col">
                <div class="card shadow-none border rounded-3 mb-0">
                  <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                      <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="wh-48 d-flex align-items-center bg-danger bg-opacity-10 justify-content-center rounded-circle">
                          <span class="material-icons-outlined text-danger">link_off</span>
                        </div>
                        <div class="">
                          <h5 class="mb-0" id="modalNotConnected">0</h5>
                          <p class="mb-0">Tidak Connected</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card shadow-none border rounded-3 mb-0">
                  <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                      <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="wh-48 d-flex align-items-center bg-warning bg-opacity-10 justify-content-center rounded-circle">
                          <span class="material-icons-outlined text-warning">sync_disabled</span>
                        </div>
                        <div class="">
                          <h5 class="mb-0" id="modalNotMirrored">0</h5>
                          <p class="mb-0">Tidak Mirrored</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card shadow-none border rounded-3 mb-0">
                  <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                      <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="wh-48 d-flex align-items-center bg-danger bg-opacity-10 justify-content-center rounded-circle">
                          <span class="material-icons-outlined text-danger">notification_important</span>
                        </div>
                        <div class="">
                          <h5 class="mb-0" id="modalCriticalWithoutAutoAlert">0</h5>
                          <p class="mb-0">Area Kritis Tanpa Auto Alert</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card shadow-none border rounded-3 mb-0">
                  <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                      <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="wh-48 d-flex align-items-center bg-info bg-opacity-10 justify-content-center rounded-circle">
                          <span class="material-icons-outlined text-info">schedule</span>
                        </div>
                        <div class="">
                          <h5 class="mb-0" id="modalNotVerified">0</h5>
                          <p class="mb-0">Belum Diverifikasi 3 Bulan</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Charts Section --}}
        <div class="row g-4 mb-4">
          <div class="col-12 col-lg-6 d-flex">
            <div class="card w-100 mb-0">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Distribusi Berdasarkan Site</h5>
                    <small class="text-muted">Jumlah CCTV per site</small>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle" data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Export</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                    </ul>
                  </div>
                </div>
                <div id="chartSiteBar" style="min-height: 350px;"></div>
              </div>
            </div>
          </div>
          <div class="col-12 col-lg-6 d-flex">
            <div class="card w-100 mb-0">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Distribusi Berdasarkan Status</h5>
                    <small class="text-muted">Persentase status CCTV</small>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle" data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Export</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                    </ul>
                  </div>
                </div>
                <div id="chartStatusPie" style="min-height: 350px;"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4 mb-4">
          <div class="col-12 col-lg-6 d-flex">
            <div class="card w-100 mb-0">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Distribusi Berdasarkan Perusahaan</h5>
                    <small class="text-muted">Jumlah CCTV per perusahaan</small>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle" data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Export</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                    </ul>
                  </div>
                </div>
                <div id="chartCompanyBar" style="min-height: 350px;"></div>
              </div>
            </div>
          </div>
          <div class="col-12 col-lg-6 d-flex">
            <div class="card w-100 mb-0">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Distribusi Berdasarkan Kondisi</h5>
                    <small class="text-muted">Persentase kondisi CCTV</small>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle" data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Export</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                    </ul>
                  </div>
                </div>
                <div id="chartKondisiPie" style="min-height: 350px;"></div>
              </div>
            </div>
          </div>
        </div>

        {{-- Additional Pie Charts --}}
        <div class="row g-4 mb-4">
          <div class="col-12 col-lg-6 d-flex">
            <div class="card w-100 mb-0">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Kategori CCTV</h5>
                    <small class="text-muted">Distribusi berdasarkan kategori</small>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle" data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Export</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                    </ul>
                  </div>
                </div>
                <div id="chartKategoriCctvPie" style="min-height: 350px;"></div>
              </div>
            </div>
          </div>
          <div class="col-12 col-lg-6 d-flex">
            <div class="card w-100 mb-0">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Kategori Area Tercapture</h5>
                    <small class="text-muted">Area Kritis vs Non Kritis</small>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle" data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Export</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                    </ul>
                  </div>
                </div>
                <div id="chartKategoriAreaPie" style="min-height: 350px;"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4 mb-4">
          <div class="col-12 col-lg-6 d-flex">
            <div class="card w-100 mb-0">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Kategori Aktivitas Tercapture</h5>
                    <small class="text-muted">Aktivitas Kritis vs Non Kritis</small>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle" data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Export</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                    </ul>
                  </div>
                </div>
                <div id="chartKategoriAktivitasPie" style="min-height: 350px;"></div>
              </div>
            </div>
          </div>
          <div class="col-12 col-lg-6 d-flex">
            <div class="card w-100 mb-0">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Tipe CCTV</h5>
                    <small class="text-muted">Fixed, PTZ, dll</small>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle" data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Export</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                    </ul>
                  </div>
                </div>
                <div id="chartTipeCctvBar" style="min-height: 350px;"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4 mb-4">
          <div class="col-12 col-lg-6 d-flex">
            <div class="card w-100 mb-0">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Jenis Instalasi</h5>
                    <small class="text-muted">Statis, Mobile, Pole-mounted, dll</small>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle" data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Export</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                    </ul>
                  </div>
                </div>
                <div id="chartJenisInstalasiBar" style="min-height: 350px;"></div>
              </div>
            </div>
          </div>
          <div class="col-12 col-lg-6 d-flex">
            <div class="card w-100 mb-0">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Tren Penambahan & Pemeliharaan CCTV</h5>
                    <small class="text-muted">Perkembangan CCTV terpasang per bulan/tahun</small>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle" data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Export</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                    </ul>
                  </div>
                </div>
                <div id="chartTimeSeries" style="min-height: 350px;"></div>
              </div>
            </div>
          </div>
        </div>

        {{-- Data Table Section --}}
        <div class="card mb-0">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="">
                <h5 class="mb-0 fw-bold">Data CCTV</h5>
                <small class="text-muted" id="companyCctvCompanyLabel">Data berdasarkan filter yang dipilih</small>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2" id="companyCctvCount">0 CCTV</span>
              </div>
            </div>
            <div class="table-responsive" style="max-height: 500px; overflow-x: auto; overflow-y: auto;">
              <table class="table table-hover align-middle mb-0" id="companyCctvTable" style="width: 100%; min-width: 1200px;">
                <thead class="table-light sticky-top">
                  <tr>
                    <th style="min-width: 50px;">No</th>
                    <th style="min-width: 100px;">Site</th>
                    <th style="min-width: 150px;">Perusahaan</th>
                    <th style="min-width: 120px;">No CCTV</th>
                    <th style="min-width: 150px;">Nama</th>
                    <th style="min-width: 100px;">Status</th>
                    <th style="min-width: 100px;">Kondisi</th>
                    <th style="min-width: 150px;">Coverage Lokasi</th>
                    <th style="min-width: 150px;">Detail Lokasi</th>
                    <th style="min-width: 150px;">Kategori Area</th>
                    <th style="min-width: 150px;">Lokasi Pemasangan</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td colspan="11" class="text-center text-muted py-4">
                      <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                      Memuat data...
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal untuk Detail Area Kerja & CCTV Coverage -->
<div class="modal fade" id="coverageDetailModal" tabindex="-1" aria-labelledby="coverageDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="coverageDetailModalLabel">Detail Area Kerja & CCTV Coverage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="coverageSummaryStats" class="mb-3">
                    <!-- Summary stats will be rendered here -->
                </div>
                <div id="coverageSearchContainer" class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="material-icons-outlined" style="font-size: 18px;">search</i></span>
                        <input type="text" class="form-control" id="coverageSearchInput" placeholder="Cari area kerja atau CCTV..." onkeyup="filterCoverageTable()">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearCoverageSearch()">
                            <i class="material-icons-outlined" style="font-size: 18px;">clear</i>
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="coverageDetailTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 25%;">Area Kerja</th>
                                <th style="width: 25%;">CCTV Coverage</th>
                                <th style="width: 18%;">Luasan (m²)</th>
                                <th style="width: 12%;">Tercover</th>
                                <th style="width: 15%;">Status</th>
                            </tr>
                        </thead>
                        <tbody id="coverageDetailTableBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                    Memuat data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Coverage table pagination" class="mt-3">
                    <ul class="pagination pagination-sm justify-content-end mb-0" id="coverageDetailPagination">
                        <!-- Pagination will be generated by JavaScript -->
                    </ul>
                </nav>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/ol@8.2.0/dist/ol.js"></script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>
<script src="{{ URL::asset('build/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ URL::asset('build/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<!-- Load BMO2 PAMA GeoJSON data -->
<script src="{{ asset('js/area-kerja-bmo2-pama.js') }}"></script>
<script src="{{ asset('js/area-cctv-bmo2-pama.js') }}"></script>
<script src="{{ asset('js/difference_bmo2-pama.js') }}"></script>
<script src="{{ asset('js/symmetrical_difference_bmo2-pama.js') }}"></script>
<script src="{{ asset('js/intersection_bmo2-pama.js') }}"></script>
<script>
    // Hazard detections data
    const hazardDetections = @json($hazardDetections);
    const cctvLocations = @json($cctvLocations);
    const insidenDataset = @json($insidenGroups);
    const insidenDatasetMap = new Map(insidenDataset.map(item => [item.no_kecelakaan, item]));
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
    let popupOverlay = null;
    
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

    // Style functions for different layers
    function getAreaKerjaStyle(feature) {
        const props = feature.getProperties();
        const areaKerja = props.area_kerja || '';
        
        let fillColor = 'rgba(16, 185, 129, 0.3)'; // Green default
        let strokeColor = '#10b981';
        
        if (areaKerja === 'Pit') {
            fillColor = 'rgba(239, 68, 68, 0.3)'; // Red
            strokeColor = '#ef4444';
        } else if (areaKerja === 'Hauling') {
            fillColor = 'rgba(245, 158, 11, 0.3)'; // Orange
            strokeColor = '#f59e0b';
        } else if (areaKerja === 'Infra Tambang') {
            fillColor = 'rgba(59, 130, 246, 0.3)'; // Blue
            strokeColor = '#3b82f6';
        }
        
        return new ol.style.Style({
            fill: new ol.style.Fill({
                color: fillColor
            }),
            stroke: new ol.style.Stroke({
                color: strokeColor,
                width: 2
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

    // Create vector layer for hazards
    hazardLayer = new ol.layer.Vector({
        source: new ol.source.Vector(),
        style: function(feature) {
            // Check site filter
            if (currentSiteFilter) {
                const hazardData = feature.get('data');
                if (hazardData) {
                    const hazardSite = hazardData.site || hazardData.nama_site || null;
                    if (hazardSite !== currentSiteFilter) {
                        return null; // Hide feature if doesn't match filter
                    }
                } else {
                    return null; // Hide if no data
                }
            }
            
            const severity = feature.get('severity');
            const status = feature.get('status');
            
            let color = '#ef4444'; // default red
            if (severity === 'critical') color = '#dc2626';
            else if (severity === 'high') color = '#f59e0b';
            else if (severity === 'medium') color = '#3b82f6';
            
            if (status === 'resolved') color = '#10b981';
            
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
    let insidenLayer = new ol.layer.Vector({
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

    // Add hazard markers
    hazardDetections.forEach(function(hazard) {
        // Skip if location is not available
        if (!hazard.location || !hazard.location.lat || !hazard.location.lng) {
            return;
        }
        
        const feature = new ol.Feature({
            geometry: new ol.geom.Point(
                ol.proj.fromLonLat([hazard.location.lng, hazard.location.lat])
            ),
            id: hazard.id,
            type: hazard.type,
            severity: hazard.severity,
            status: hazard.status,
            description: hazard.description,
            data: hazard
        });
        hazardLayer.getSource().addFeature(feature);
    });

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

    cctvLocations.forEach(function(cctv) {
        const feature = new ol.Feature({
            geometry: new ol.geom.Point(
                ol.proj.fromLonLat(cctv.location)
            ),
            name: cctv.name || cctv.cctv_name || cctv.nama_cctv || 'CCTV',
            type: 'cctv',
            cctvData: cctv  // Store full CCTV data for icon and popup
        });
        cctvLayer.getSource().addFeature(feature);
    });

    // Add insiden markers
    insidenDataset.forEach(function (insiden) {
        if (!insiden.latitude || !insiden.longitude) {
            return;
        }

        const feature = new ol.Feature({
            geometry: new ol.geom.Point(
                ol.proj.fromLonLat([parseFloat(insiden.longitude), parseFloat(insiden.latitude)])
            ),
            type: 'insiden',
            insidenId: insiden.no_kecelakaan,
            data: insiden
        });

        insidenLayer.getSource().addFeature(feature);
    });

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
            populateCoverageTable();
        }
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
        if (!intersectionBmo2PamaLayer || !areaKerjaBmo2PamaLayer) {
            document.getElementById('coverageTableBody').innerHTML = `
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
        if (coverageTableData.length > 0) {
            document.getElementById('btnShowDetail').style.display = 'block';
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
        
        // Summary mode: show only first item
        if (coverageTableData.length > 0) {
            const firstItem = coverageTableData[0];
            const statusBadge = firstItem.cctvCount > 0 
                ? '<span class="badge bg-success">Covered</span>' 
                : '<span class="badge bg-warning">Partial</span>';
            
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <strong class="text-primary">${firstItem.areaKerjaNama || firstItem.lokasi}</strong>
                                    ${statusBadge}
                                </div>
                                <div class="text-muted small">
                                    <div><strong>CCTV Coverage:</strong> ${firstItem.cctvNames || 'N/A'}</div>
                                    <div><strong>Luasan:</strong> ${formatArea(firstItem.totalArea)}</div>
                                    <div><strong>Total CCTV:</strong> ${firstItem.cctvCount} unit</div>
                                </div>
                            </div>
                        </div>
                    </td>
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
    const siteFilter = document.getElementById('siteFilter');
    let currentSiteFilter = '';

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
            
            // Check if it's a CCTV marker
            if (featureType === 'cctv') {
                const cctv = feature.get('cctvData');
                if (cctv) {
                    showCCTVPopup(evt.coordinate, cctv);
                }
                return;
            }
            
            // Check if it's a hazard
            const data = feature.get('data');
            if (data) {
                // Clear area kerja highlight when clicking hazard
                if (highlightedAreaKerjaLayer) {
                    map.removeLayer(highlightedAreaKerjaLayer);
                    highlightedAreaKerjaLayer = null;
                }
                showHazardPopup(evt.coordinate, data);
                return;
            }
            
            // Check if it's a GeoJSON polygon (Area Kerja or Area CCTV)
            const props = feature.getProperties();
            if (props.nomor_cctv !== undefined || props.id_lokasi !== undefined) {
                let content = '';
                
                if (props.nomor_cctv !== undefined) {
                    // Area CCTV
                    content = `
                        <h6 style="margin: 0 0 10px 0;">Area CCTV</h6>
                        <p style="margin: 5px 0; font-size: 13px;"><strong>Nomor CCTV:</strong> ${props.nomor_cctv || 'N/A'}</p>
                        <p style="margin: 5px 0; font-size: 13px;"><strong>Nama CCTV:</strong> ${props.nama_cctv || 'N/A'}</p>
                        <p style="margin: 5px 0; font-size: 13px;"><strong>Site:</strong> ${props.site || 'N/A'}</p>
                        <p style="margin: 5px 0; font-size: 13px;"><strong>Perusahaan:</strong> ${props.perusahaan_cctv || 'N/A'}</p>
                        <p style="margin: 5px 0; font-size: 13px;"><strong>Luasan:</strong> ${props.luasan ? props.luasan.toLocaleString('id-ID', {maximumFractionDigits: 2}) : 'N/A'} m²</p>
                    `;
                } else if (props.id_lokasi !== undefined) {
                    // Area Kerja
                    content = `
                        <h6 style="margin: 0 0 10px 0;">Area Kerja</h6>
                        <p style="margin: 5px 0; font-size: 13px;"><strong>Lokasi:</strong> ${props.lokasi || 'N/A'}</p>
                        <p style="margin: 5px 0; font-size: 13px;"><strong>ID Lokasi:</strong> ${props.id_lokasi || 'N/A'}</p>
                        <p style="margin: 5px 0; font-size: 13px;"><strong>Site:</strong> ${props.site || 'N/A'}</p>
                        <p style="margin: 5px 0; font-size: 13px;"><strong>Perusahaan:</strong> ${props.perusahaan || 'N/A'}</p>
                        <p style="margin: 5px 0; font-size: 13px;"><strong>Area Kerja:</strong> ${props.area_kerja || 'N/A'}</p>
                        <p style="margin: 5px 0; font-size: 13px;"><strong>Luasan:</strong> ${props.luasan ? props.luasan.toLocaleString('id-ID', {maximumFractionDigits: 2}) : 'N/A'} m²</p>
                        <p style="margin: 5px 0; font-size: 13px;"><strong>Upload Data:</strong> ${props.upload_data || 'N/A'}</p>
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
        
        // Update CCTV display with animation - ambil dari database MySQL (bukan dari WMS)
        // Gunakan API yang sama dengan modal detail untuk konsistensi
        // Gunakan site dari parameter fungsi (filter site), atau '__all__' jika tidak ada filter
        const company = currentSelectedCompany || '__all__';
        // Convert empty string to '__all__' untuk konsistensi dengan API
        const siteParam = (site && site.trim()) ? site.trim() : '__all__';
        
        // Ambil total CCTV tanpa filter untuk menghitung persentase
        Promise.all([
            fetch(`{{ route('hazard-detection.api.cctv-chart-stats') }}?company=${encodeURIComponent(company)}&site=${encodeURIComponent(siteParam)}`),
            fetch(`{{ route('hazard-detection.api.cctv-chart-stats') }}?company=${encodeURIComponent(company)}&site=__all__`)
        ])
            .then(responses => Promise.all(responses.map(r => r.json())))
            .then(([filteredData, totalData]) => {
                if (filteredData.success && totalData.success) {
                    const filteredCctv = filteredData.total || 0;
                    const totalCctv = totalData.total || 0;
                    const cctvCount = filteredCctv; // CCTV yang terfilter
                    // Hitung persentase: (filtered / total) * 100, sama seperti donut chart lainnya
                    const cctvPercentage = totalCctv > 0 ? Math.round((filteredCctv / totalCctv) * 100) : (filteredCctv > 0 ? 100 : 0);
                    
                    // Update CCTV display with animation
                    const statCctvCount = document.getElementById('statCctvCount');
                    const statCctvChange = document.getElementById('statCctvChange');
                    const statCctvText = document.getElementById('statCctvText');
                    if (statCctvCount) {
                        animateNumber('statCctvCount', cctvCount, 800);
                    }
                    if (statCctvChange) {
                        statCctvChange.textContent = cctvPercentage.toFixed(1) + '%';
                    }
                    if (statCctvText) {
                        statCctvText.textContent = cctvCount.toLocaleString('id-ID') + ' cctv';
                    }
                    
                    // Update donut chart untuk CCTV dengan animasi berdasarkan persentase
                    const currentCctvDonutValue = donutChartState.donutCctv || 0;
                    
                    // Jika nilai saat ini sama dengan target, reset sedikit untuk memicu animasi
                    if (Math.abs(currentCctvDonutValue - cctvPercentage) < 0.1) {
                        donutChartState.donutCctv = Math.max(0, cctvPercentage - 1);
                    }
                    
                    // Update donut chart dengan animasi
                    updateDonutChart('donutCctv', cctvPercentage, '#6f42c1');
                } else {
                    console.error('Error: API returned unsuccessful response');
                }
            })
            .catch(error => {
                console.error('Error loading CCTV stats:', error);
                // Fallback ke data lama jika API error
                const cctvCount = filteredCctv.length;
                const totalCctv = cctvLocations.length;
                const cctvPercentage = totalCctv > 0 ? Math.round((cctvCount / totalCctv) * 100) : 100;
                
                const statCctvCount = document.getElementById('statCctvCount');
                const statCctvChange = document.getElementById('statCctvChange');
                const statCctvText = document.getElementById('statCctvText');
                if (statCctvCount) {
                    animateNumber('statCctvCount', cctvCount, 800);
                }
                if (statCctvChange) {
                    statCctvChange.textContent = cctvPercentage + '%';
                }
                if (statCctvText) {
                    statCctvText.textContent = cctvCount + ' cctv';
                }
                
                // Update donut chart untuk CCTV dengan animasi (fallback case)
                const currentCctvDonutValue = donutChartState.donutCctv || 0;
                if (Math.abs(currentCctvDonutValue - cctvPercentage) < 0.1) {
                    donutChartState.donutCctv = Math.max(0, cctvPercentage - 1);
                }
                updateDonutChart('donutCctv', cctvPercentage, '#6f42c1');
            });
        
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
    function animateNumber(elementId, targetValue, duration = 800) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        // Get current value from state or parse from element
        let currentValue = numberAnimationState[elementId];
        if (currentValue === undefined || currentValue === null) {
            const currentText = element.textContent || '0';
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
            element.textContent = targetValue.toLocaleString('id-ID');
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
            
            // Update the element with formatted number
            element.textContent = currentValue.toLocaleString('id-ID');
            
            // Continue animation if not finished
            if (progress < 1) {
                numberAnimationFrames[elementId] = requestAnimationFrame(animate);
            } else {
                // Animation complete, update state
                numberAnimationState[elementId] = endValue;
                element.textContent = endValue.toLocaleString('id-ID');
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

    // Event listener for site filter
    if (siteFilter) {
        siteFilter.addEventListener('change', function(e) {
            const selectedSite = e.target.value || '';
            // Update currentSelectedSite untuk digunakan di API
            currentSelectedSite = selectedSite || '__all__';
            filterBySite(selectedSite);
        });
    }

    // Initialize site filter on page load
    setTimeout(function() {
        populateSiteFilter();
        
        // Initialize donut chart states with initial values (100% for all data)
        const totalHazards = hazardDetections.length;
        const totalCctv = cctvLocations.length;
        const totalInsiden = insidenDataset.length;
        const totalGr = hazardDetections.filter(function(h) {
            return h.nama_goldenrule && h.nama_goldenrule !== 'N/A' && h.nama_goldenrule !== '';
        }).length;
        
        // Set initial state - CCTV akan diinisialisasi dari 0 untuk animasi
        donutChartState.donutHazard = totalHazards > 0 ? 100 : 0;
        donutChartState.donutCctv = 0; // Mulai dari 0 agar animasi terlihat saat data dimuat
        donutChartState.donutInsiden = totalInsiden > 0 ? 100 : 0;
        donutChartState.donutGr = totalGr > 0 ? 100 : 0;
        
        // Initialize number animation states with initial values
        numberAnimationState.statHazardCount = totalHazards;
        numberAnimationState.statCctvCount = totalCctv;
        numberAnimationState.statInsidenCount = totalInsiden;
        numberAnimationState.statGrCount = totalGr;
        
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
                        const statusBadgeClass = hazard.status === 'active' ? 'bg-danger' : 'bg-success';
                        
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
        
        // Add click event listener to CCTV stat card
        const cctvStatCard = document.getElementById('cctvStatCard');
        if (cctvStatCard) {
            cctvStatCard.addEventListener('click', function() {
                openCctvDetailModal();
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

    // Function untuk membuka modal detail CCTV
    function openCctvDetailModal(perusahaan = null) {
        const modal = new bootstrap.Modal(document.getElementById('totalCctvModal'));
        
        // Reset filters
        currentSelectedCompany = '__all__';
        currentSelectedSite = '__all__';
        
        // Set filter values if perusahaan provided
        if (perusahaan && perusahaan !== 'N/A') {
            currentSelectedCompany = perusahaan;
        }
        
        modal.show();
        
        // Wait for modal to be shown, then load data
        const modalElement = document.getElementById('totalCctvModal');
        const onModalShown = function() {
            // Set filter dropdowns
            const filterCompanyEl = document.getElementById('filterCompany');
            const filterSiteEl = document.getElementById('filterSite');
            
            if (filterCompanyEl) {
                filterCompanyEl.value = currentSelectedCompany;
            }
            if (filterSiteEl) {
                filterSiteEl.value = '__all__';
            }
            
            // Load filter options first, then load data
            loadFilterOptions();
            
            // Update label
            updateFilterLabel();
            
            // Load initial data after a short delay to ensure modal is fully rendered
            setTimeout(() => {
                loadChartStats();
            }, 500);
        };
        
        // Add event listener (will be removed after first use)
        modalElement.addEventListener('shown.bs.modal', onModalShown, { once: true });
    }
    
    // Function untuk load filter options
    function loadFilterOptions() {
        // Load companies
        fetch('{{ route("hazard-detection.api.company-overview") }}')
            .then(response => response.json())
            .then(data => {
                const companySelect = document.getElementById('filterCompany');
                companySelect.innerHTML = '<option value="__all__">Semua Perusahaan</option>';
                
                if (data.success && data.data.length > 0) {
                    data.data.forEach(company => {
                        const option = document.createElement('option');
                        option.value = company.perusahaan;
                        option.textContent = company.perusahaan;
                        companySelect.appendChild(option);
                    });
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
                siteSelect.innerHTML = '<option value="__all__">Semua Site</option>';
                
                if (data.success && data.data.length > 0) {
                    data.data.forEach(site => {
                        const option = document.createElement('option');
                        option.value = site;
                        option.textContent = site;
                        siteSelect.appendChild(option);
                    });
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

        // Handler untuk filter otomatis saat dropdown berubah
        // Menggunakan event delegation untuk memastikan event listener selalu bekerja
        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'filterCompany') {
                currentSelectedCompany = e.target.value || '__all__';
                updateFilterLabel();
                loadChartStats();
            }
            
            if (e.target && e.target.id === 'filterSite') {
                currentSelectedSite = e.target.value || '__all__';
                updateFilterLabel();
                loadChartStats();
            }
        });
        
        // Handler untuk reset filter
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
                
                updateFilterLabel();
                
                // Load chart stats
                loadChartStats();
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

    // Real-time notification untuk APD detections
    (function() {
        let lastCheckTime = null; // Mulai dengan null untuk mendapatkan semua data pertama kali
        let notificationContainer = null;
        let checkInterval = null;
        let isFirstCheck = true;

        // Buat container untuk notification
        function createNotificationContainer() {
            if (!notificationContainer) {
                notificationContainer = document.createElement('div');
                notificationContainer.className = 'notification-container';
                document.body.appendChild(notificationContainer);
            }
            return notificationContainer;
        }

        // Fungsi untuk menampilkan notification
        function showNotification(data) {
            console.log('Showing notification with data:', data);
            const container = createNotificationContainer();
            
            // Format waktu
            const now = new Date();
            const timeStr = now.toLocaleTimeString('id-ID', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });

            // Buat elemen notification
            const notification = document.createElement('div');
            notification.className = 'notification-item';
            
            // Tentukan icon berdasarkan tipe
            let icon = 'warning';
            let title = 'Peringatan Deteksi APD';
            let message = `Terdeteksi tidak menggunakan APD`;
            
            if (data.data && data.data.length > 0) {
                const firstItem = data.data[0];
                
                // Format pesan berdasarkan struktur tabel no_apd_detections
                if (firstItem.detection_time) {
                    const detectionDate = new Date(firstItem.detection_time);
                    const timeInfo = detectionDate.toLocaleTimeString('id-ID', { 
                        hour: '2-digit', 
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    
                    let confidenceInfo = '';
                    if (firstItem.confidence_score) {
                        const confidence = parseFloat(firstItem.confidence_score.toString().replace(',', '.'));
                        confidenceInfo = ` (Tingkat Keyakinan: ${(confidence * 100).toFixed(1)}%)`;
                    }
                    
                    message = `Terdeteksi tidak menggunakan APD pada ${timeInfo}${confidenceInfo}`;
                } else if (firstItem.created_at) {
                    const createdDate = new Date(firstItem.created_at);
                    const timeInfo = createdDate.toLocaleTimeString('id-ID', { 
                        hour: '2-digit', 
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    message = `Terdeteksi tidak menggunakan APD pada ${timeInfo}`;
                }
            }
            
            // Jika ada lebih dari 1 deteksi
            if (data.count > 1) {
                message += ` (${data.count} deteksi)`;
            }

            notification.innerHTML = `
                <div class="notification-icon">
                    <i class="material-icons-outlined" style="font-size: 20px;">notifications_active</i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${title}</div>
                    <div class="notification-message">${message}</div>
                    <div class="notification-time">${timeStr}</div>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i class="material-icons-outlined" style="font-size: 18px;">close</i>
                </button>
            `;

            container.appendChild(notification);
            console.log('Notification added to DOM');

            // Auto-hide setelah 5 detik
            setTimeout(() => {
                notification.classList.add('hiding');
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 300);
            }, 5000);
        }

        // Fungsi untuk check data baru
        async function checkNewDetections() {
            try {
                let apiUrl = '{{ route("hazard-detection.api.check-new-apd-detections") }}';
                const params = new URLSearchParams();
                
                if (lastCheckTime) {
                    params.append('last_check_time', lastCheckTime);
                }
                
                // Untuk testing, bisa tambahkan ?test=1 di URL
                if (window.location.search.includes('test=1')) {
                    params.append('test', '1');
                }
                
                if (params.toString()) {
                    apiUrl += '?' + params.toString();
                }
                
                console.log('Checking new APD detections...', { 
                    lastCheckTime, 
                    isFirstCheck,
                    apiUrl 
                });
                
                const response = await fetch(apiUrl);
                
                if (!response.ok) {
                    throw new Error('Failed to fetch: ' + response.status);
                }

                const result = await response.json();
                console.log('APD Detection API Response:', result);
                
                // Pada check pertama, set lastCheckTime tanpa menampilkan notifikasi
                if (isFirstCheck) {
                    console.log('First check - setting lastCheckTime without notification');
                    lastCheckTime = result.last_check_time || new Date().toISOString();
                    isFirstCheck = false;
                    return;
                }
                
                // Hanya tampilkan notifikasi jika benar-benar ada data baru
                // Pastikan: success = true, has_new = true, count > 0, dan ada data array yang tidak kosong
                const hasNewData = result.success === true && 
                                  result.has_new === true && 
                                  result.count > 0 && 
                                  result.data && 
                                  Array.isArray(result.data) && 
                                  result.data.length > 0;
                
                if (hasNewData) {
                    console.log('New APD detections found!', result);
                    showNotification(result);
                    // Update last check time setelah menampilkan notifikasi
                    lastCheckTime = result.last_check_time || new Date().toISOString();
                } else {
                    console.log('No new APD detections found', {
                        success: result.success,
                        has_new: result.has_new,
                        count: result.count,
                        hasData: result.data && result.data.length > 0
                    });
                    // Update last check time meskipun tidak ada data baru, untuk check berikutnya
                    if (result.last_check_time) {
                        lastCheckTime = result.last_check_time;
                    }
                }
            } catch (error) {
                console.error('Error checking new APD detections:', error);
            }
        }

        // Mulai polling setiap 10 detik
        function startPolling() {
            console.log('Starting APD detection polling...');
            
            // Check pertama kali setelah 2 detik
            setTimeout(() => {
                console.log('First check after 2 seconds...');
                checkNewDetections();
            }, 2000);

            // Set interval untuk check setiap 10 detik
            checkInterval = setInterval(() => {
                console.log('Periodic check (every 10 seconds)...');
                checkNewDetections();
            }, 10000);
        }

        // Start polling ketika halaman sudah load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM loaded, starting polling...');
                startPolling();
            });
        } else {
            console.log('DOM already loaded, starting polling immediately...');
            startPolling();
        }

        // Cleanup ketika halaman di-unload
        window.addEventListener('beforeunload', () => {
            if (checkInterval) {
                clearInterval(checkInterval);
            }
        });

        // Fungsi test untuk manual testing (bisa dipanggil dari console)
        window.testApdNotification = function() {
            console.log('Testing APD notification...');
            showNotification({
                success: true,
                has_new: true,
                count: 1,
                data: [{
                    id: 999,
                    detection_time: new Date().toISOString(),
                    confidence_score: '0.85',
                    created_at: new Date().toISOString()
                }],
                last_check_time: new Date().toISOString()
            });
        };

        // Log info untuk debugging
        console.log('APD Detection Notification System Initialized');
        console.log('To test notification manually, run: testApdNotification()');
    })();
</script>
@endsection



