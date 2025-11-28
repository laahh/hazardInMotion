@extends('layouts.masterMotionHazardAdmin')

@section('title', 'CCTV Management - Status - Beraucoal')

@section('css')
<style>
    .cctv-status-header {
        margin-bottom: 24px;
    }

    .cctv-status-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .cctv-status-subtitle {
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

    .stats-card.online {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .stats-card.offline {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }

    .stats-card.coordinates {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
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

    .cctv-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        transition: all 0.3s ease;
        background: white;
    }

    .cctv-card:hover {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .cctv-card.online {
        border-left: 4px solid #10b981;
    }

    .cctv-card.offline {
        border-left: 4px solid #ef4444;
    }

    .cctv-card.unknown {
        border-left: 4px solid #6b7280;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-online {
        background-color: #d1fae5;
        color: #065f46;
    }

    .status-offline {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .status-unknown {
        background-color: #f3f4f6;
        color: #6b7280;
    }

    .condition-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .condition-baik {
        background-color: #d1fae5;
        color: #065f46;
    }

    .condition-rusak {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .condition-unknown {
        background-color: #f3f4f6;
        color: #6b7280;
    }

    .filter-controls {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .cctv-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #e5e7eb;
    }

    .detail-item {
        display: flex;
        flex-direction: column;
    }

    .detail-label {
        font-size: 12px;
        color: #6b7280;
        font-weight: 500;
        margin-bottom: 4px;
    }

    .detail-value {
        font-size: 14px;
        color: #111827;
        font-weight: 600;
    }

    .cctv-actions {
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

    .btn-view {
        background-color: #3b82f6;
        color: white;
    }

    .btn-view:hover {
        background-color: #2563eb;
    }

    .btn-test {
        background-color: #10b981;
        color: white;
    }

    .btn-test:hover {
        background-color: #059669;
    }

    .btn-edit {
        background-color: #f59e0b;
        color: white;
    }

    .btn-edit:hover {
        background-color: #d97706;
    }
</style>
@endsection

@section('content')
<div class="cctv-status-header">
    <h1 class="cctv-status-title">CCTV Management - Status</h1>
    <p class="cctv-status-subtitle">Monitor and manage CCTV status and connectivity</p>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-md-3">
        <div class="stats-card total">
            <div class="stats-number">{{ $stats['total_cctv'] }}</div>
            <div class="stats-label">Total CCTV</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card online">
            <div class="stats-number">{{ $stats['online_cctv'] }}</div>
            <div class="stats-label">Online CCTV</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card offline">
            <div class="stats-number">{{ $stats['offline_cctv'] }}</div>
            <div class="stats-label">Offline CCTV</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card coordinates">
            <div class="stats-number">{{ $stats['with_coordinates'] }}</div>
            <div class="stats-label">With Coordinates</div>
        </div>
    </div>
</div>

<!-- Filter Controls -->
<div class="filter-controls">
    <div class="row">
        <div class="col-md-3">
            <label for="statusFilter" class="form-label">Status Filter</label>
            <select id="statusFilter" class="form-select">
                <option value="all">All Status</option>
                <option value="online">Online</option>
                <option value="offline">Offline</option>
                <option value="unknown">Unknown</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="conditionFilter" class="form-label">Condition Filter</label>
            <select id="conditionFilter" class="form-select">
                <option value="all">All Conditions</option>
                <option value="Baik">Baik</option>
                <option value="Rusak">Rusak</option>
                <option value="Unknown">Unknown</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="siteFilter" class="form-label">Site Filter</label>
            <select id="siteFilter" class="form-select">
                <option value="all">All Sites</option>
                @foreach(array_unique(array_column($cctvStatus, 'site')) as $site)
                <option value="{{ $site }}">{{ $site }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="searchInput" class="form-label">Search</label>
            <input type="text" id="searchInput" class="form-control" placeholder="Search CCTV...">
        </div>
    </div>
</div>

<!-- CCTV Status List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">CCTV Status Monitoring</h5>
        <button class="btn btn-sm btn-primary" onclick="refreshStatus()">
            <i class="material-icons-outlined">refresh</i> Refresh Status
        </button>
    </div>
    <div class="card-body">
        <div id="cctvStatusList">
            @foreach($cctvStatus as $cctv)
            @php
                $isOnline = ($cctv['status'] === 'Live View' || $cctv['connected'] === 'Yes' || $cctv['kondisi'] === 'Baik');
                $statusClass = $isOnline ? 'online' : ($cctv['status'] === 'Unknown' ? 'unknown' : 'offline');
                $statusText = $isOnline ? 'Online' : ($cctv['status'] === 'Unknown' ? 'Unknown' : 'Offline');
            @endphp
            <div class="cctv-card {{ $statusClass }}" 
                 data-cctv-id="{{ $cctv['id'] }}"
                 data-status="{{ $statusText }}"
                 data-condition="{{ $cctv['kondisi'] }}"
                 data-site="{{ $cctv['site'] }}"
                 data-name="{{ strtolower($cctv['nama_cctv']) }}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">{{ $cctv['nama_cctv'] }}</h6>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                            <span class="status-badge status-{{ strtolower($statusText) }}">
                                {{ $statusText }}
                            </span>
                            <span class="condition-badge condition-{{ strtolower($cctv['kondisi']) }}">
                                {{ $cctv['kondisi'] }}
                            </span>
                            <span class="text-muted" style="font-size: 13px;">{{ $cctv['no_cctv'] }}</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">Last checked:</small>
                        <small class="text-muted">{{ $cctv['last_checked'] }}</small>
                    </div>
                </div>
                
                <div class="cctv-details">
                    <div class="detail-item">
                        <span class="detail-label">Site</span>
                        <span class="detail-value">{{ $cctv['site'] }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Perusahaan</span>
                        <span class="detail-value">{{ $cctv['perusahaan'] }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status</span>
                        <span class="detail-value">{{ $cctv['status'] }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Connected</span>
                        <span class="detail-value">{{ $cctv['connected'] }}</span>
                    </div>
                    @if($cctv['lokasi_pemasangan'])
                    <div class="detail-item">
                        <span class="detail-label">Location</span>
                        <span class="detail-value">{{ $cctv['lokasi_pemasangan'] }}</span>
                    </div>
                    @endif
                    @if($cctv['control_room'])
                    <div class="detail-item">
                        <span class="detail-label">Control Room</span>
                        <span class="detail-value">{{ $cctv['control_room'] }}</span>
                    </div>
                    @endif
                    @if($cctv['longitude'] && $cctv['latitude'])
                    <div class="detail-item">
                        <span class="detail-label">Coordinates</span>
                        <span class="detail-value">{{ $cctv['latitude'] }}, {{ $cctv['longitude'] }}</span>
                    </div>
                    @endif
                </div>
                
                <div class="cctv-actions">
                    <button class="btn-action btn-view" onclick="viewCCTVDetails('{{ $cctv['id'] }}')">
                        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">visibility</i>
                        View Details
                    </button>
                    <button class="btn-action btn-test" onclick="testCCTVConnection('{{ $cctv['id'] }}')">
                        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">network_check</i>
                        Test Connection
                    </button>
                    <a href="{{ route('cctv-data.edit', $cctv['id']) }}" class="btn-action btn-edit">
                        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">edit</i>
                        Edit
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function refreshStatus() {
        location.reload();
    }

    function viewCCTVDetails(cctvId) {
        window.location.href = '{{ route("cctv-data.show", "") }}/' + cctvId;
    }

    function testCCTVConnection(cctvId) {
        if (confirm('Test connection to this CCTV?')) {
            alert('Testing connection for CCTV ID: ' + cctvId);
            // Test connection logic
        }
    }

    // Filter functionality
    document.getElementById('statusFilter').addEventListener('change', filterCCTV);
    document.getElementById('conditionFilter').addEventListener('change', filterCCTV);
    document.getElementById('siteFilter').addEventListener('change', filterCCTV);
    document.getElementById('searchInput').addEventListener('input', filterCCTV);

    function filterCCTV() {
        const statusFilter = document.getElementById('statusFilter').value;
        const conditionFilter = document.getElementById('conditionFilter').value;
        const siteFilter = document.getElementById('siteFilter').value;
        const searchInput = document.getElementById('searchInput').value.toLowerCase();
        
        const cctvCards = document.querySelectorAll('.cctv-card');
        cctvCards.forEach(function(card) {
            const status = card.getAttribute('data-status');
            const condition = card.getAttribute('data-condition');
            const site = card.getAttribute('data-site');
            const name = card.getAttribute('data-name');
            
            let show = true;
            
            if (statusFilter !== 'all' && status.toLowerCase() !== statusFilter.toLowerCase()) show = false;
            if (conditionFilter !== 'all' && condition !== conditionFilter) show = false;
            if (siteFilter !== 'all' && site !== siteFilter) show = false;
            if (searchInput && !name.includes(searchInput)) show = false;
            
            card.style.display = show ? 'block' : 'none';
        });
    }
</script>
@endsection

