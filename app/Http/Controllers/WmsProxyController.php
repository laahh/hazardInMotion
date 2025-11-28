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
        $allowedDomains = [
            'sgi.beraucoal.co.id'
        ];
        
        $parsedUrl = parse_url($wmsUrl);
        $host = $parsedUrl['host'] ?? '';
        
        if (!in_array($host, $allowedDomains)) {
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

