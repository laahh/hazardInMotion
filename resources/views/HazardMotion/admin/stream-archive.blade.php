@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Live Streaming - Stream Archive - Beraucoal')

@section('css')
<style>
    .archive-header {
        margin-bottom: 24px;
    }

    .archive-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .archive-subtitle {
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

    .stats-card.recordings {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .stats-card.duration {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .stats-card.size {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    }

    .stats-card.avg-duration {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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

    .filter-section {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .filter-row {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .filter-group {
        flex: 1;
        min-width: 200px;
    }

    .filter-group label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: #374151;
        margin-bottom: 8px;
    }

    .filter-group select,
    .filter-group input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
    }

    .archive-table {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .table {
        margin-bottom: 0;
    }

    .table thead {
        background: #f9fafb;
    }

    .table thead th {
        font-weight: 600;
        color: #374151;
        border-bottom: 2px solid #e5e7eb;
        padding: 12px 16px;
        font-size: 14px;
    }

    .table tbody td {
        padding: 12px 16px;
        font-size: 14px;
        color: #6b7280;
        vertical-align: middle;
    }

    .table tbody tr:hover {
        background: #f9fafb;
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

    .status-interrupted {
        background-color: #fef3c7;
        color: #92400e;
    }

    .status-error {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .quality-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        background-color: #dbeafe;
        color: #1e40af;
    }

    .btn-action {
        padding: 4px 8px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        background: white;
        color: #374151;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-action:hover {
        background: #f3f4f6;
        color: #111827;
    }

    .btn-action.download {
        border-color: #3b82f6;
        color: #3b82f6;
    }

    .btn-action.download:hover {
        background: #dbeafe;
    }

    .btn-action.delete {
        border-color: #ef4444;
        color: #ef4444;
    }

    .btn-action.delete:hover {
        background: #fee2e2;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6b7280;
    }

    .empty-state i {
        font-size: 64px;
        color: #d1d5db;
        margin-bottom: 16px;
    }

    .empty-state h3 {
        font-size: 20px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .empty-state p {
        font-size: 14px;
        color: #6b7280;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="archive-header">
        <h1 class="archive-title">Stream Archive</h1>
        <p class="archive-subtitle">View and manage recorded CCTV streams</p>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card recordings">
                <div class="stats-number">{{ $stats['total_recordings'] }}</div>
                <div class="stats-label">Total Recordings</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card duration">
                <div class="stats-number">{{ $stats['total_duration'] }}</div>
                <div class="stats-label">Total Duration</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card size">
                <div class="stats-number">{{ $stats['total_size'] }}</div>
                <div class="stats-label">Total Size</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card avg-duration">
                <div class="stats-number">{{ $stats['avg_duration'] }}</div>
                <div class="stats-label">Average Duration</div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <div class="filter-row">
            <div class="filter-group">
                <label>Date Range</label>
                <input type="date" id="filterDateFrom" class="form-control">
            </div>
            <div class="filter-group">
                <label>To</label>
                <input type="date" id="filterDateTo" class="form-control">
            </div>
            <div class="filter-group">
                <label>Site</label>
                <select id="filterSite" class="form-control">
                    <option value="">All Sites</option>
                    @foreach($cctvList->pluck('site')->unique() as $site)
                        <option value="{{ $site }}">{{ $site }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select id="filterStatus" class="form-control">
                    <option value="">All Status</option>
                    <option value="completed">Completed</option>
                    <option value="interrupted">Interrupted</option>
                    <option value="error">Error</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Search</label>
                <input type="text" id="searchArchive" class="form-control" placeholder="Search by CCTV name...">
            </div>
        </div>
    </div>

    <!-- Archive Table -->
    <div class="archive-table">
        @if(count($archivedStreams) > 0)
            <table class="table">
                <thead>
                    <tr>
                        <th>CCTV Name</th>
                        <th>Site</th>
                        <th>Location</th>
                        <th>Started At</th>
                        <th>Ended At</th>
                        <th>Duration</th>
                        <th>File Size</th>
                        <th>Quality</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="archiveTableBody">
                    @foreach($archivedStreams as $stream)
                    <tr data-site="{{ $stream['site'] }}" data-status="{{ $stream['status'] }}" data-name="{{ strtolower($stream['cctv_name']) }}" data-date="{{ $stream['started_at'] }}">
                        <td>
                            <strong>{{ $stream['cctv_name'] }}</strong>
                        </td>
                        <td>{{ $stream['site'] }}</td>
                        <td>{{ $stream['location'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($stream['started_at'])->format('Y-m-d H:i:s') }}</td>
                        <td>{{ \Carbon\Carbon::parse($stream['ended_at'])->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $stream['duration'] }}</td>
                        <td>{{ $stream['file_size'] }}</td>
                        <td>
                            <span class="quality-badge">{{ $stream['quality'] }}</span>
                        </td>
                        <td>
                            @if($stream['status'] === 'completed')
                                <span class="status-badge status-completed">Completed</span>
                            @elseif($stream['status'] === 'interrupted')
                                <span class="status-badge status-interrupted">Interrupted</span>
                            @else
                                <span class="status-badge status-error">Error</span>
                            @endif
                        </td>
                        <td>
                            <div style="display: flex; gap: 4px;">
                                <a href="javascript:;" class="btn-action download" onclick="downloadArchive({{ $stream['id'] }})" title="Download">
                                    <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">download</i>
                                </a>
                                <a href="javascript:;" class="btn-action" onclick="viewArchive({{ $stream['id'] }})" title="View">
                                    <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">play_arrow</i>
                                </a>
                                <a href="javascript:;" class="btn-action delete" onclick="deleteArchive({{ $stream['id'] }})" title="Delete">
                                    <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">delete</i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-state">
                <i class="material-icons-outlined">archive</i>
                <h3>No Archived Streams</h3>
                <p>Recorded streams will appear here</p>
            </div>
        @endif
    </div>
</div>
@endsection

@section('js')
<script>
    // Filter functionality
    document.getElementById('filterDateFrom')?.addEventListener('change', filterArchive);
    document.getElementById('filterDateTo')?.addEventListener('change', filterArchive);
    document.getElementById('filterSite')?.addEventListener('change', filterArchive);
    document.getElementById('filterStatus')?.addEventListener('change', filterArchive);
    document.getElementById('searchArchive')?.addEventListener('input', filterArchive);

    function filterArchive() {
        const dateFrom = document.getElementById('filterDateFrom')?.value || '';
        const dateTo = document.getElementById('filterDateTo')?.value || '';
        const siteFilter = document.getElementById('filterSite')?.value.toLowerCase() || '';
        const statusFilter = document.getElementById('filterStatus')?.value.toLowerCase() || '';
        const searchFilter = document.getElementById('searchArchive')?.value.toLowerCase() || '';
        
        const rows = document.querySelectorAll('#archiveTableBody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const site = row.getAttribute('data-site')?.toLowerCase() || '';
            const status = row.getAttribute('data-status')?.toLowerCase() || '';
            const name = row.getAttribute('data-name') || '';
            const date = row.getAttribute('data-date') || '';

            const matchSite = !siteFilter || site === siteFilter;
            const matchStatus = !statusFilter || status === statusFilter;
            const matchSearch = !searchFilter || name.includes(searchFilter);
            
            let matchDate = true;
            if (dateFrom && date) {
                const streamDate = new Date(date.split(' ')[0]);
                const fromDate = new Date(dateFrom);
                matchDate = streamDate >= fromDate;
            }
            if (dateTo && date && matchDate) {
                const streamDate = new Date(date.split(' ')[0]);
                const toDate = new Date(dateTo);
                matchDate = streamDate <= toDate;
            }

            if (matchSite && matchStatus && matchSearch && matchDate) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
    }

    function downloadArchive(archiveId) {
        // TODO: Implement download functionality
        alert('Download archive ID: ' + archiveId);
    }

    function viewArchive(archiveId) {
        // TODO: Implement view/play functionality
        alert('View archive ID: ' + archiveId);
    }

    function deleteArchive(archiveId) {
        if (!confirm('Are you sure you want to delete this archived stream?')) {
            return;
        }

        // TODO: Implement delete functionality
        alert('Delete archive ID: ' + archiveId);
    }
</script>
@endsection

