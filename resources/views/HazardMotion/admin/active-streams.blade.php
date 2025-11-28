@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Live Streaming - Active Streams - Beraucoal')

@section('css')
<style>
    .streams-header {
        margin-bottom: 24px;
    }

    .streams-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .streams-subtitle {
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

    .stats-card.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .stats-card.viewers {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .stats-card.bandwidth {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    }

    .stats-card.quality {
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

    .stream-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 16px;
        transition: all 0.3s ease;
        background: white;
    }

    .stream-card:hover {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .stream-card.streaming {
        border-left: 4px solid #10b981;
    }

    .stream-preview {
        width: 100%;
        height: 200px;
        background: #000;
        border-radius: 8px;
        margin-bottom: 16px;
        position: relative;
        overflow: hidden;
    }

    .stream-preview img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .stream-preview .stream-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 14px;
    }

    .stream-info {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .stream-title {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 4px;
    }

    .stream-meta {
        font-size: 14px;
        color: #6b7280;
    }

    .stream-stats {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .stream-stat {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: #6b7280;
    }

    .stream-stat i {
        font-size: 18px;
        color: #9ca3af;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-streaming {
        background-color: #d1fae5;
        color: #065f46;
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

    .btn-start-stream {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-start-stream:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);
    }

    .stream-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }

    .btn-action {
        padding: 6px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: white;
        color: #374151;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-action:hover {
        background: #f3f4f6;
    }

    .btn-action.stop {
        border-color: #ef4444;
        color: #ef4444;
    }

    .btn-action.stop:hover {
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
    <div class="streams-header">
        <h1 class="streams-title">Active Streams</h1>
        <p class="streams-subtitle">Monitor and manage live CCTV streams in real-time</p>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card active">
                <div class="stats-number" id="totalActive">{{ $stats['total_active'] }}</div>
                <div class="stats-label">Active Streams</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card viewers">
                <div class="stats-number" id="totalViewers">{{ $stats['total_viewers'] }}</div>
                <div class="stats-label">Total Viewers</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card bandwidth">
                <div class="stats-number" id="totalBandwidth">{{ $stats['total_bandwidth'] }}</div>
                <div class="stats-label">Total Bandwidth</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card quality">
                <div class="stats-number" id="avgQuality">{{ $stats['avg_quality'] }}</div>
                <div class="stats-label">Average Quality</div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <div class="filter-row">
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
                <label>Quality</label>
                <select id="filterQuality" class="form-control">
                    <option value="">All Quality</option>
                    <option value="SD">SD</option>
                    <option value="HD">HD</option>
                    <option value="Full HD">Full HD</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Search</label>
                <input type="text" id="searchStream" class="form-control" placeholder="Search by CCTV name...">
            </div>
            <div class="filter-group">
                <button class="btn-start-stream" onclick="showStartStreamModal()">
                    <i class="material-icons-outlined" style="vertical-align: middle; margin-right: 4px;">play_arrow</i>
                    Start New Stream
                </button>
            </div>
        </div>
    </div>

    <!-- Active Streams List -->
    <div id="streamsList">
        @if(count($activeStreams) > 0)
            @foreach($activeStreams as $stream)
            <div class="stream-card streaming" data-site="{{ $stream['site'] }}" data-quality="{{ $stream['quality'] }}" data-name="{{ strtolower($stream['cctv_name']) }}">
                <div class="stream-preview" id="preview-{{ $stream['id'] }}">
                    @if($stream['rtsp_url'])
                        <img src="{{ route('cctv-proxy-rtsp') }}?rtsp={{ urlencode($stream['rtsp_url']) }}&transport=tcp" 
                             alt="{{ $stream['cctv_name'] }}"
                             onerror="handleStreamError(this, {{ $stream['id'] }}, '{{ $stream['link_akses'] }}')">
                    @else
                        <div class="stream-overlay">
                            <div>
                                <i class="material-icons-outlined" style="font-size: 48px; margin-bottom: 8px;">videocam_off</i>
                                <p>No RTSP URL available</p>
                                @if($stream['link_akses'])
                                    <a href="{{ $stream['link_akses'] }}" target="_blank" style="color: #3b82f6; text-decoration: underline;">Open Link Access</a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
                <div class="stream-info">
                    <div>
                        <div class="stream-title">{{ $stream['cctv_name'] }}</div>
                        <div class="stream-meta">
                            <span>{{ $stream['site'] }}</span> • 
                            <span>{{ $stream['location'] }}</span>
                        </div>
                    </div>
                    <div>
                        <span class="status-badge status-streaming">Streaming</span>
                        <span class="quality-badge" style="margin-left: 8px;">{{ $stream['quality'] }}</span>
                    </div>
                </div>
                <div class="stream-stats">
                    <div class="stream-stat">
                        <i class="material-icons-outlined">people</i>
                        <span>{{ $stream['viewers'] }} viewers</span>
                    </div>
                    <div class="stream-stat">
                        <i class="material-icons-outlined">speed</i>
                        <span>{{ $stream['bitrate'] }}</span>
                    </div>
                    <div class="stream-stat">
                        <i class="material-icons-outlined">schedule</i>
                        <span>Started: {{ \Carbon\Carbon::parse($stream['started_at'])->format('H:i') }}</span>
                    </div>
                    <div class="stream-stat">
                        <i class="material-icons-outlined">timer</i>
                        <span>Duration: {{ $stream['duration'] }}</span>
                    </div>
                </div>
                <div class="stream-actions">
                    <button class="btn-action" onclick="viewStreamDetails({{ $stream['id'] }})">
                        <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">info</i>
                        Details
                    </button>
                    <button class="btn-action" onclick="fullscreenStream({{ $stream['id'] }})">
                        <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">fullscreen</i>
                        Fullscreen
                    </button>
                    <button class="btn-action stop" onclick="stopStream({{ $stream['id'] }})">
                        <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">stop</i>
                        Stop
                    </button>
                </div>
            </div>
            @endforeach
        @else
            <div class="empty-state">
                <i class="material-icons-outlined">videocam_off</i>
                <h3>No Active Streams</h3>
                <p>Start a new stream to begin monitoring CCTV cameras</p>
                <button class="btn-start-stream" onclick="showStartStreamModal()" style="margin-top: 16px;">
                    <i class="material-icons-outlined" style="vertical-align: middle; margin-right: 4px;">play_arrow</i>
                    Start New Stream
                </button>
            </div>
        @endif
    </div>
</div>

<!-- Start Stream Modal -->
<div class="modal fade" id="startStreamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Start New Stream</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="startStreamForm">
                    @csrf
                    <div class="mb-3">
                        <label for="streamCctvId" class="form-label">Select CCTV</label>
                        <select class="form-control" id="streamCctvId" name="cctv_id" required>
                            <option value="">-- Select CCTV --</option>
                            @foreach($cctvList as $cctv)
                                <option value="{{ $cctv->id }}" data-rtsp="{{ $cctv->link_akses ? 'yes' : 'no' }}">
                                    {{ $cctv->nama_cctv }} - {{ $cctv->site }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="streamQuality" class="form-label">Quality</label>
                        <select class="form-control" id="streamQuality" name="quality">
                            <option value="SD">SD</option>
                            <option value="HD" selected>HD</option>
                            <option value="Full HD">Full HD</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="startStream()">Start Stream</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    // Filter functionality
    document.getElementById('filterSite').addEventListener('change', filterStreams);
    document.getElementById('filterQuality').addEventListener('change', filterStreams);
    document.getElementById('searchStream').addEventListener('input', filterStreams);

    function filterStreams() {
        const siteFilter = document.getElementById('filterSite').value.toLowerCase();
        const qualityFilter = document.getElementById('filterQuality').value.toLowerCase();
        const searchFilter = document.getElementById('searchStream').value.toLowerCase();
        
        const streamCards = document.querySelectorAll('.stream-card');
        let visibleCount = 0;

        streamCards.forEach(card => {
            const site = card.getAttribute('data-site')?.toLowerCase() || '';
            const quality = card.getAttribute('data-quality')?.toLowerCase() || '';
            const name = card.getAttribute('data-name') || '';

            const matchSite = !siteFilter || site === siteFilter;
            const matchQuality = !qualityFilter || quality === qualityFilter;
            const matchSearch = !searchFilter || name.includes(searchFilter);

            if (matchSite && matchQuality && matchSearch) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
    }

    function handleStreamError(img, streamId, linkAkses) {
        const container = img.parentElement;
        container.innerHTML = `
            <div class="stream-overlay">
                <div>
                    <i class="material-icons-outlined" style="font-size: 48px; margin-bottom: 8px;">error_outline</i>
                    <p>Failed to load stream</p>
                    ${linkAkses ? `<a href="${linkAkses}" target="_blank" style="color: #3b82f6; text-decoration: underline;">Open Link Access</a>` : ''}
                </div>
            </div>
        `;
    }

    function showStartStreamModal() {
        const modal = new bootstrap.Modal(document.getElementById('startStreamModal'));
        modal.show();
    }

    function startStream() {
        const form = document.getElementById('startStreamForm');
        const formData = new FormData(form);

        fetch('{{ route("live-streaming.start") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || formData.get('_token')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Stream started successfully!');
                location.reload();
            } else {
                alert('Failed to start stream: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to start stream');
        });
    }

    function stopStream(streamId) {
        if (!confirm('Are you sure you want to stop this stream?')) {
            return;
        }

        fetch(`{{ route("live-streaming.stop", ":id") }}`.replace(':id', streamId), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Stream stopped successfully!');
                location.reload();
            } else {
                alert('Failed to stop stream: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to stop stream');
        });
    }

    function viewStreamDetails(streamId) {
        // TODO: Implement stream details view
        alert('Stream details for ID: ' + streamId);
    }

    function fullscreenStream(streamId) {
        const preview = document.getElementById(`preview-${streamId}`);
        if (preview.requestFullscreen) {
            preview.requestFullscreen();
        } else if (preview.webkitRequestFullscreen) {
            preview.webkitRequestFullscreen();
        } else if (preview.mozRequestFullScreen) {
            preview.mozRequestFullScreen();
        } else if (preview.msRequestFullscreen) {
            preview.msRequestFullscreen();
        }
    }

    // Auto-refresh streams every 30 seconds
    setInterval(function() {
        fetch('{{ route("live-streaming.api.active") }}')
            .then(response => response.json())
            .then(data => {
                // Update statistics
                document.getElementById('totalActive').textContent = data.length;
                // TODO: Update other stats and stream list
            })
            .catch(error => console.error('Error refreshing streams:', error));
    }, 30000);
</script>
@endsection

