<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class CctvProxyController extends Controller
{
    /**
     * Proxy untuk CCTV snapshot/image
     * Mengatasi masalah browser security dengan basic auth di URL
     */
    public function snapshot(Request $request)
    {
        $ip = $request->get('ip');
        $port = $request->get('port', 80);
        $channel = $request->get('channel', 101);
        $username = $request->get('username');
        $password = $request->get('password');
        
        // Validasi parameter
        if (!$ip || !$username || !$password) {
            return response()->json(['error' => 'Missing required parameters'], 400);
        }
        
        // Validasi IP address
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return response()->json(['error' => 'Invalid IP address'], 400);
        }
        
        // Pastikan IP adalah private/internal network (untuk keamanan)
        // Allow private IP ranges: 192.168.x.x, 10.x.x.x, 172.16-31.x.x
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            // IP is public, might want to restrict this
            // For now, allow it but you might want to add whitelist
        }
        
        try {
            // Format channel untuk HikVision
            // Channel bisa dalam format: 101 (channel 1), 102 (channel 2), atau langsung 1, 2, etc
            // HikVision menggunakan format: {channel}01 untuk main stream, {channel}02 untuk sub stream
            // Jika channel >= 100, ambil 2 digit terakhir, jika < 100, gunakan langsung
            
            $channelNum = (int)$channel;
            if ($channelNum >= 100) {
                // Channel format: 101, 102, etc -> ambil 2 digit terakhir
                $channelNum = (int)substr($channel, -2);
            }
            
            // Format channel untuk URL: {channel}01 (main stream)
            $channelFormat = str_pad($channelNum, 2, '0', STR_PAD_LEFT) . '01';
            
            // Coba beberapa URL format yang umum digunakan HikVision
            $urls = [
                "http://{$ip}:{$port}/Streaming/channels/{$channelFormat}/picture",
                "http://{$ip}:{$port}/ISAPI/Streaming/channels/{$channelFormat}/picture",
                "http://{$ip}:{$port}/Streaming/channels/{$channel}01/picture",
                "http://{$ip}:{$port}/ISAPI/Streaming/channels/{$channel}01/picture",
            ];
            
            $lastError = null;
            $attempts = [];
            
            foreach ($urls as $snapshotUrl) {
                foreach (['basic', 'digest'] as $authType) {
                    try {
                        $client = Http::timeout(10)
                            ->accept('image/*');
                        
                        if ($authType === 'basic') {
                            $client = $client->withBasicAuth($username, $password);
                        } else {
                            $client = $client->withOptions([
                                'auth' => [$username, $password, 'digest']
                            ]);
                        }
                        
                        $response = $client->get($snapshotUrl);
                        
                        $attempts[] = [
                            'url' => $snapshotUrl,
                            'auth' => $authType,
                            'status' => $response->status(),
                        ];
                        
                        if ($response->successful()) {
                            $contentType = $response->header('Content-Type') ?? 'image/jpeg';
                            
                            if (strpos($contentType, 'image/') === 0 || $response->body()) {
                                return response($response->body(), 200)
                                    ->header('Content-Type', $contentType)
                                    ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                                    ->header('Pragma', 'no-cache')
                                    ->header('Expires', '0')
                                    ->header('Access-Control-Allow-Origin', '*');
                            }
                        } else {
                            $lastError = 'HTTP '.$response->status();
                            Log::warning('CCTV snapshot proxy received non-success response', [
                                'ip' => $ip,
                                'port' => $port,
                                'channel' => $channel,
                                'auth_type' => $authType,
                                'status' => $response->status(),
                            ]);
                        }
                    } catch (\Exception $e) {
                        $lastError = $e->getMessage();
                        $attempts[] = [
                            'url' => $snapshotUrl,
                            'auth' => $authType,
                            'exception' => $e->getMessage(),
                        ];
                        Log::warning('CCTV snapshot proxy exception', [
                            'ip' => $ip,
                            'port' => $port,
                            'channel' => $channel,
                            'auth_type' => $authType,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }
                }
            }
            
            Log::error('CCTV snapshot proxy failed for all attempts', [
                'ip' => $ip,
                'port' => $port,
                'channel' => $channel,
                'attempts' => $attempts,
                'last_error' => $lastError,
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch snapshot from CCTV',
                'message' => $lastError ?? 'All URL formats failed',
                'attempts' => $attempts,
            ], 502);
            
        } catch (\Exception $e) {
            Log::error('CCTV snapshot proxy unexpected error', [
                'ip' => $ip,
                'port' => $port,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'Proxy error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Proxy untuk CCTV RTSP stream (jika diperlukan)
     * Note: Browser tidak support RTSP langsung, perlu transcoder
     */
    public function stream(Request $request)
    {
        // Placeholder untuk future implementation
        // Akan memerlukan RTSP to WebRTC atau HLS transcoder
        return response()->json(['error' => 'Not implemented yet'], 501);
    }

    /**
     * Proxy RTSP stream menggunakan ffmpeg dan output MPJPEG
     * Mengembalikan multipart/x-mixed-replace agar bisa dirender oleh <img>
     */
    public function rtspStream(Request $request)
    {
        $rtspUrl = $request->get('rtsp');
        $transport = $request->get('transport', 'tcp');
        $quality = (int) $request->get('quality', 5);
        $quality = max(2, min($quality, 31));

        if (!$rtspUrl) {
            return response()->json(['error' => 'Missing RTSP URL'], 400);
        }

        // Decode URL encoded RTSP
        $rtspUrl = urldecode($rtspUrl);

        $ffmpegPath = config('services.ffmpeg.path', 'ffmpeg');

        $command = [
            $ffmpegPath,
            '-loglevel', 'error',
            '-rtsp_transport', $transport,
            '-i', $rtspUrl,
            '-f', 'mpjpeg',
            '-q:v', (string) $quality,
            '-'
        ];

        Log::info('Starting RTSP proxy stream', [
            'rtsp' => $this->maskRtsp($rtspUrl),
            'transport' => $transport,
            'quality' => $quality,
        ]);

        $response = new StreamedResponse(function () use ($command, $rtspUrl) {
            $process = new Process($command);
            $process->setTimeout(null);
            $process->setIdleTimeout(null);

            try {
                $process->start();

                while ($process->isRunning()) {
                    $output = $process->getIncrementalOutput();
                    if ($output !== '') {
                        echo $output;
                        if (function_exists('flush')) {
                            flush();
                        }
                        if (function_exists('ob_flush')) {
                            @ob_flush();
                        }
                    }

                    $errorOutput = $process->getIncrementalErrorOutput();
                    if ($errorOutput !== '') {
                        Log::warning('FFmpeg rtsp proxy error output', [
                            'rtsp' => $this->maskRtsp($rtspUrl),
                            'error' => $errorOutput,
                        ]);
                    }

                    usleep(20000); // 20ms
                }

                if (!$process->isSuccessful()) {
                    Log::error('FFmpeg rtsp proxy exited with error', [
                        'rtsp' => $this->maskRtsp($rtspUrl),
                        'exit_code' => $process->getExitCode(),
                        'error_output' => $process->getErrorOutput(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('RTSP proxy stream unexpected error', [
                    'rtsp' => $this->maskRtsp($rtspUrl),
                    'error' => $e->getMessage(),
                ]);
            } finally {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        });

        $response->headers->set('Content-Type', 'multipart/x-mixed-replace; boundary=ffserver');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }

    /**
     * Ensure RTSP stream is being transcoded to HLS segments and return playlist URL.
     */
    public function rtspHls(Request $request)
    {
        $rtspUrl = $request->get('rtsp');
        $transport = $request->get('transport', 'tcp');

        if (!$rtspUrl) {
            return response()->json(['error' => 'Missing RTSP URL'], 400);
        }

        $rtspUrl = urldecode($rtspUrl);
        $hash = md5($rtspUrl);
        $storagePath = storage_path("app/public/hls/{$hash}");
        $playlistPath = "{$storagePath}/index.m3u8";

        if (!File::exists($storagePath)) {
            File::makeDirectory($storagePath, 0755, true, true);
        }

        $playlistFresh = File::exists($playlistPath) && (time() - File::lastModified($playlistPath) < 10);

        if (!$playlistFresh) {
            $this->startBackgroundHlsProcess($rtspUrl, $storagePath, $playlistPath, $transport);
        }

        // Beri waktu ffmpeg menulis playlist pertama kali
        $waitStart = microtime(true);
        while (!File::exists($playlistPath) && microtime(true) - $waitStart < 5) {
            usleep(200000); // 200ms
        }

        if (!File::exists($playlistPath)) {
            return response()->json([
                'error' => 'Failed to generate HLS playlist. Check ffmpeg process or RTSP connectivity.'
            ], 500);
        }

        $publicUrl = asset("storage/hls/{$hash}/index.m3u8");

        return response()->json([
            'playlist_url' => $publicUrl,
            'key' => $hash,
        ]);
    }

    protected function startBackgroundHlsProcess(string $rtspUrl, string $storagePath, string $playlistPath, string $transport = 'tcp'): void
    {
        $ffmpegPath = config('services.ffmpeg.path', 'ffmpeg');
        $segmentPattern = $storagePath . DIRECTORY_SEPARATOR . 'segment_%05d.ts';

        // Hapus playlist/segment lama agar tidak terjadi overlap
        $existingFiles = File::glob($storagePath . DIRECTORY_SEPARATOR . '*') ?: [];
        foreach ($existingFiles as $oldFile) {
            @File::delete($oldFile);
        }

        $arguments = [
            escapeshellarg($ffmpegPath),
            '-loglevel warning',
            '-rtsp_transport ' . escapeshellarg($transport),
            '-i ' . escapeshellarg($rtspUrl),
            '-c:v copy',
            '-an',
            '-f hls',
            '-hls_time 2',
            '-hls_list_size 5',
            '-hls_flags delete_segments+append_list+omit_endlist',
            '-hls_segment_filename ' . escapeshellarg($segmentPattern),
            escapeshellarg($playlistPath),
        ];

        $command = $this->buildDetachedCommand($arguments);

        try {
            if (stripos(PHP_OS, 'WIN') === 0) {
                pclose(popen($command, 'r'));
            } else {
                exec($command);
            }
        } catch (\Throwable $th) {
            Log::error('Failed to start background HLS process', [
                'rtsp' => $this->maskRtsp($rtspUrl),
                'error' => $th->getMessage(),
            ]);
        }
    }

    protected function buildDetachedCommand(array $arguments): string
    {
        $binary = array_shift($arguments);
        $cmd = $binary . ' ' . implode(' ', $arguments);

        if (stripos(PHP_OS, 'WIN') === 0) {
            return 'start /B "" ' . $cmd;
        }

        return $cmd . ' > /dev/null 2>&1 &';
    }

    /**
     * Get snapshot from RTSP stream using ffmpeg
     * Returns a single JPEG frame from RTSP stream
     */
    public function rtspSnapshot(Request $request)
    {
        $rtspUrl = $request->get('rtsp');
        $transport = $request->get('transport', 'tcp');
        $quality = (int) $request->get('quality', 5);
        $quality = max(2, min($quality, 31));

        if (!$rtspUrl) {
            return response()->json(['error' => 'Missing RTSP URL'], 400);
        }

        // Decode URL encoded RTSP
        $rtspUrl = urldecode($rtspUrl);

        $ffmpegPath = config('services.ffmpeg.path', 'ffmpeg');

        // Use ffmpeg to capture a single frame from RTSP stream
        $command = [
            $ffmpegPath,
            '-loglevel', 'error',
            '-rtsp_transport', $transport,
            '-i', $rtspUrl,
            '-vframes', '1',
            '-f', 'image2',
            '-q:v', (string) $quality,
            '-'
        ];

        Log::info('Capturing RTSP snapshot', [
            'rtsp' => $this->maskRtsp($rtspUrl),
            'transport' => $transport,
        ]);

        try {
            $process = new Process($command);
            $process->setTimeout(10);
            $process->run();

            if ($process->isSuccessful()) {
                $imageData = $process->getOutput();
                
                if (!empty($imageData)) {
                    return response($imageData, 200)
                        ->header('Content-Type', 'image/jpeg')
                        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                        ->header('Pragma', 'no-cache')
                        ->header('Expires', '0')
                        ->header('Access-Control-Allow-Origin', '*');
                }
            }

            $errorOutput = $process->getErrorOutput();
            Log::error('FFmpeg RTSP snapshot failed', [
                'rtsp' => $this->maskRtsp($rtspUrl),
                'exit_code' => $process->getExitCode(),
                'error' => $errorOutput,
            ]);

            return response()->json([
                'error' => 'Failed to capture snapshot from RTSP stream',
                'message' => $errorOutput ?: 'FFmpeg process failed',
            ], 502);

        } catch (\Exception $e) {
            Log::error('RTSP snapshot unexpected error', [
                'rtsp' => $this->maskRtsp($rtspUrl),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Snapshot error: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function maskRtsp(string $rtspUrl): string
    {
        $parsed = parse_url($rtspUrl);
        if (!$parsed) {
            return $rtspUrl;
        }

        $userInfo = '';
        if (!empty($parsed['user'])) {
            $userInfo = $parsed['user'];
            if (!empty($parsed['pass'])) {
                $userInfo .= ':******';
            }
            $userInfo .= '@';
        }

        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        return sprintf('rtsp://%s%s%s%s', $userInfo, $host, $port, $path . $query);
    }
}

