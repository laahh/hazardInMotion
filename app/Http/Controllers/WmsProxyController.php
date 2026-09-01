<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Response;

class WmsProxyController extends Controller
{
    /**
     * Proxy untuk WMS GetCapabilities dan GetMap
     * Mengatasi masalah CORS dengan membuat request dari server side
     */
    public function proxy(Request $request)
    {
        // Ambil parameter dari request
        $wmsUrl = $request->get('url');
        $params = $request->except(['url']);
        
        // Validasi URL untuk keamanan
        if (!$wmsUrl || !filter_var($wmsUrl, FILTER_VALIDATE_URL)) {
            return response()->json(['error' => 'Invalid URL'], 400);
        }
        
        // Pastikan URL adalah dari domain yang diizinkan
        $parsedUrl = parse_url($wmsUrl);
        $host = strtolower((string) ($parsedUrl['host'] ?? ''));
        $scheme = strtolower((string) ($parsedUrl['scheme'] ?? ''));
        $path = (string) ($parsedUrl['path'] ?? '');
        $port = (int) ($parsedUrl['port'] ?? ($scheme === 'https' ? 443 : 80));

        $allowed = $host === 'sgi.beraucoal.co.id' && $scheme === 'https';
        if ($host === '10.10.10.61' && $scheme === 'http' && $port === 8080 && str_starts_with($path, '/geoserver/')) {
            $allowed = true;
        }

        if (! $allowed) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }
        
        try {
            // Buat request ke WMS server
            $response = Http::timeout(30)->get($wmsUrl, $params);
            
            // Ambil content type dari response
            $contentType = $response->header('Content-Type') ?? 'text/xml';
            
            // Return response dengan header yang sesuai
            return response($response->body(), $response->status())
                ->header('Content-Type', $contentType)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type');
                
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Proxy error: ' . $e->getMessage()
            ], 500);
        }
    }
}

