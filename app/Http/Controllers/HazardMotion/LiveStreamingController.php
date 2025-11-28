<?php

namespace App\Http\Controllers\HazardMotion;

use App\Http\Controllers\Controller;
use App\Models\CctvData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiveStreamingController extends Controller
{
    /**
     * Display active streams page
     */
    public function activeStreams()
    {
        // Get CCTV data with RTSP URLs
        $cctvList = CctvData::whereNotNull('link_akses')
            ->where('status', '!=', 'Offline')
            ->select('id', 'nama_cctv', 'site', 'lokasi_pemasangan', 'link_akses', 'user_name', 'password', 'status', 'kondisi', 'longitude', 'latitude')
            ->get();

        // Mock data for active streams (dalam implementasi nyata, ini akan diambil dari database atau session)
        $activeStreams = [];
        foreach ($cctvList->take(10) as $cctv) {
            $activeStreams[] = [
                'id' => $cctv->id,
                'cctv_id' => $cctv->id,
                'cctv_name' => $cctv->nama_cctv,
                'site' => $cctv->site,
                'location' => $cctv->lokasi_pemasangan,
                'status' => 'streaming',
                'quality' => 'HD',
                'bitrate' => rand(1000, 5000) . ' kbps',
                'viewers' => rand(0, 50),
                'started_at' => now()->subMinutes(rand(5, 120))->format('Y-m-d H:i:s'),
                'duration' => $this->calculateDuration(now()->subMinutes(rand(5, 120))),
                'rtsp_url' => $this->buildRtspUrl($cctv),
                'link_akses' => $cctv->link_akses,
            ];
        }

        // Statistics
        $stats = [
            'total_active' => count($activeStreams),
            'total_viewers' => array_sum(array_column($activeStreams, 'viewers')),
            'total_bandwidth' => array_sum(array_map(function($stream) {
                return (int)str_replace([' kbps', ' '], '', $stream['bitrate']);
            }, $activeStreams)) . ' kbps',
            'avg_quality' => 'HD',
        ];

        return view('HazardMotion.admin.active-streams', compact('activeStreams', 'stats', 'cctvList'));
    }

    /**
     * Display stream archive page
     */
    public function streamArchive()
    {
        // Get CCTV data
        $cctvList = CctvData::select('id', 'nama_cctv', 'site', 'lokasi_pemasangan')
            ->get();

        // Mock data for archived streams
        $archivedStreams = [];
        for ($i = 1; $i <= 20; $i++) {
            $cctv = $cctvList->random();
            $startTime = now()->subDays(rand(1, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            $duration = rand(30, 360); // minutes
            
            $archivedStreams[] = [
                'id' => $i,
                'cctv_id' => $cctv->id,
                'cctv_name' => $cctv->nama_cctv,
                'site' => $cctv->site,
                'location' => $cctv->lokasi_pemasangan,
                'started_at' => $startTime->format('Y-m-d H:i:s'),
                'ended_at' => $startTime->copy()->addMinutes($duration)->format('Y-m-d H:i:s'),
                'duration' => $this->formatDuration($duration),
                'file_size' => $this->formatFileSize(rand(100, 5000) * 1024 * 1024), // MB
                'quality' => ['SD', 'HD', 'Full HD'][rand(0, 2)],
                'status' => ['completed', 'interrupted', 'error'][rand(0, 2)],
                'recorded_by' => 'System',
            ];
        }

        // Sort by started_at descending
        usort($archivedStreams, function($a, $b) {
            return strcmp($b['started_at'], $a['started_at']);
        });

        // Statistics
        $stats = [
            'total_recordings' => count($archivedStreams),
            'total_duration' => $this->formatTotalDuration(array_sum(array_map(function($stream) {
                return $this->parseDuration($stream['duration']);
            }, $archivedStreams))),
            'total_size' => $this->formatFileSize(array_sum(array_map(function($stream) {
                return $this->parseFileSize($stream['file_size']);
            }, $archivedStreams))),
            'avg_duration' => $this->formatDuration(array_sum(array_map(function($stream) {
                return $this->parseDuration($stream['duration']);
            }, $archivedStreams)) / count($archivedStreams)),
        ];

        return view('HazardMotion.admin.stream-archive', compact('archivedStreams', 'stats', 'cctvList'));
    }

    /**
     * API endpoint to get active streams
     */
    public function getActiveStreams(Request $request)
    {
        $cctvList = CctvData::whereNotNull('link_akses')
            ->where('status', '!=', 'Offline')
            ->select('id', 'nama_cctv', 'site', 'lokasi_pemasangan', 'link_akses', 'user_name', 'password', 'status', 'kondisi', 'longitude', 'latitude')
            ->get();

        $activeStreams = [];
        foreach ($cctvList->take(10) as $cctv) {
            $activeStreams[] = [
                'id' => $cctv->id,
                'cctv_id' => $cctv->id,
                'cctv_name' => $cctv->nama_cctv,
                'site' => $cctv->site,
                'location' => $cctv->lokasi_pemasangan,
                'status' => 'streaming',
                'quality' => 'HD',
                'bitrate' => rand(1000, 5000) . ' kbps',
                'viewers' => rand(0, 50),
                'started_at' => now()->subMinutes(rand(5, 120))->format('Y-m-d H:i:s'),
                'duration' => $this->calculateDuration(now()->subMinutes(rand(5, 120))),
                'rtsp_url' => $this->buildRtspUrl($cctv),
                'link_akses' => $cctv->link_akses,
            ];
        }

        return response()->json($activeStreams);
    }

    /**
     * Start a new stream
     */
    public function startStream(Request $request)
    {
        $request->validate([
            'cctv_id' => 'required|exists:cctv_data_bmo2,id',
        ]);

        $cctv = CctvData::findOrFail($request->cctv_id);

        // In real implementation, this would start the stream and return stream info
        return response()->json([
            'success' => true,
            'message' => 'Stream started successfully',
            'stream' => [
                'id' => uniqid(),
                'cctv_id' => $cctv->id,
                'cctv_name' => $cctv->nama_cctv,
                'rtsp_url' => $this->buildRtspUrl($cctv),
                'started_at' => now()->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Stop a stream
     */
    public function stopStream(Request $request, $streamId)
    {
        // In real implementation, this would stop the stream
        return response()->json([
            'success' => true,
            'message' => 'Stream stopped successfully',
        ]);
    }

    /**
     * Build RTSP URL from CCTV data
     */
    private function buildRtspUrl($cctv)
    {
        if (!$cctv->link_akses) {
            return null;
        }

        // If link_akses is already RTSP URL, return it
        if (strpos($cctv->link_akses, 'rtsp://') === 0) {
            return $cctv->link_akses;
        }

        // Try to extract IP and port from link_akses
        $parsed = parse_url($cctv->link_akses);
        if ($parsed && isset($parsed['host'])) {
            $ip = $parsed['host'];
            $port = $parsed['port'] ?? 554;
            $username = $cctv->user_name ?? 'admin';
            $password = $cctv->password ?? 'admin';
            
            // Default RTSP path for HikVision
            $path = '/Streaming/Channels/101';
            
            return "rtsp://{$username}:{$password}@{$ip}:{$port}{$path}";
        }

        return null;
    }

    /**
     * Calculate duration from start time
     */
    private function calculateDuration($startTime)
    {
        $diff = now()->diffInMinutes($startTime);
        return $this->formatDuration($diff);
    }

    /**
     * Format duration in minutes to human readable
     */
    private function formatDuration($minutes)
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($hours < 24) {
            return $hours . 'h ' . $mins . 'm';
        }
        
        $days = floor($hours / 24);
        $hours = $hours % 24;
        
        return $days . 'd ' . $hours . 'h';
    }

    /**
     * Format total duration
     */
    private function formatTotalDuration($minutes)
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($hours < 24) {
            return $hours . 'h ' . $mins . 'm';
        }
        
        $days = floor($hours / 24);
        $hours = $hours % 24;
        
        return $days . 'd ' . $hours . 'h';
    }

    /**
     * Parse duration string to minutes
     */
    private function parseDuration($duration)
    {
        $minutes = 0;
        
        // Parse format like "2h 30m" or "30 min"
        if (preg_match('/(\d+)d/', $duration, $matches)) {
            $minutes += (int)$matches[1] * 24 * 60;
        }
        if (preg_match('/(\d+)h/', $duration, $matches)) {
            $minutes += (int)$matches[1] * 60;
        }
        if (preg_match('/(\d+)m/', $duration, $matches)) {
            $minutes += (int)$matches[1];
        }
        if (preg_match('/(\d+)\s*min/', $duration, $matches)) {
            $minutes += (int)$matches[1];
        }
        
        return $minutes;
    }

    /**
     * Format file size
     */
    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Parse file size string to bytes
     */
    private function parseFileSize($size)
    {
        $units = ['B' => 1, 'KB' => 1024, 'MB' => 1024 * 1024, 'GB' => 1024 * 1024 * 1024, 'TB' => 1024 * 1024 * 1024 * 1024];
        
        if (preg_match('/([\d.]+)\s*([A-Z]+)/i', $size, $matches)) {
            $value = (float)$matches[1];
            $unit = strtoupper($matches[2]);
            
            if (isset($units[$unit])) {
                return $value * $units[$unit];
            }
        }
        
        return 0;
    }
}

